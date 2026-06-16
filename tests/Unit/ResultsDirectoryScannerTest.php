<?php

namespace Tests\Unit;

use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileScanAction;
use App\DataObjects\Import\ResultFileVersion;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use App\Services\ResultsDirectoryScanner;
use DateTimeImmutable;
use Lsr\Core\Config;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ResultsDirectoryScannerTest extends TestCase
{
    public function testZeroGameFileIsRejectedAsPreparedLoadFile(): void {
        $dir = sys_get_temp_dir() . '/lac-results-scanner-' . uniqid('', true);
        mkdir($dir);
        $file = $dir . '/0000.game';
        file_put_contents($file, '');

        try {
            $result = $this->createScanner()->scanFile($file);

            $this->assertSame(0, $result->seen);
            $this->assertSame(0, $result->queued);
            $this->assertSame(1, $result->invalid);
            $this->assertSame(
                'Skipping file with invalid name ending with 0000.game',
                $result->errors[0]->message
            );
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }

    public function testProcessingStateExpiresAfterTtl(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000')
            )
        );
    }

    private function createScanner(): ResultsDirectoryScanner {
        return new ResultsDirectoryScanner(
            new ResultFileVersionFactory(),
            $this->createStub(ResultFileImportStateRepository::class),
            $this->createScannerConfig(),
        );
    }

    private function isProcessingExpired(
        ResultsDirectoryScanner $scanner,
        ResultFileImportState   $state,
        DateTimeImmutable       $now,
    ): bool {
        $method = new ReflectionMethod(ResultsDirectoryScanner::class, 'isProcessingExpired');

        return $method->invoke($scanner, $state, $now);
    }

    private function createState(
        ResultFileImportStatus $status,
        ?DateTimeImmutable     $processingStartedAt = null,
        ?DateTimeImmutable     $queuedAt = null,
        ?string                $processedVersion = null,
    ): ResultFileImportState {
        $seenVersion = sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64));

        return new ResultFileImportState(
            id: 1,
            path: '/tmp/results/0001.game',
            pathHash: sha1('/tmp/results/0001.game'),
            system: 'evo6',
            seenMtime: 123,
            seenSize: 456,
            seenHash: str_repeat('a', 64),
            seenVersion: $seenVersion,
            status: $status,
            attempts: 1,
            queuedAt: $queuedAt,
            processingStartedAt: $processingStartedAt,
            processedVersion: $processedVersion,
        );
    }

    public function testFreshProcessingStateDoesNotExpire(): void {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@800')),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testProcessingStateWithoutStartTimeExpires(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testNonProcessingStateDoesNotExpire(): void {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::QUEUED, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testOldQueuedStateExpiresWhenUnprocessed(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(
                    ResultFileImportStatus::QUEUED,
                    queuedAt: new DateTimeImmutable('@800'),
                ),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testFreshQueuedStateDoesNotExpire(): void {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(
                    ResultFileImportStatus::QUEUED,
                    queuedAt: new DateTimeImmutable('@950'),
                ),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testSeenStateWithoutQueueTimeExpiresWhenUnprocessed(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::SEEN),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testImportedStateDoesNotExpire(): void {
        $scanner = $this->createScanner();
        $state = $this->createState(ResultFileImportStatus::IMPORTED);

        $this->assertFalse(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(
                    ResultFileImportStatus::IMPORTED,
                    processedVersion: $state->seenVersion,
                ),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testOldStartedStateExpiresWhenUnprocessed(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(
                    ResultFileImportStatus::STARTED,
                    queuedAt: new DateTimeImmutable('@800'),
                ),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testImportedSameVersionDecisionIsUnchangedImported(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(
                ResultFileImportStatus::IMPORTED,
                processedVersion: $version->version,
            )
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::SKIP, $decision->action);
        $this->assertSame('unchanged-imported', $decision->reason);
    }

    public function testFreshQueuedDecisionIsQueuedFresh(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::QUEUED, queuedAt: new DateTimeImmutable('@950'))
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::SKIP, $decision->action);
        $this->assertSame('queued-fresh', $decision->reason);
    }

    public function testExpiredQueuedDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::QUEUED, queuedAt: new DateTimeImmutable('@800'))
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::QUEUE, $decision->action);
        $this->assertSame('queued-expired-requeued', $decision->reason);
    }

    public function testExpiredProcessingDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@600'))
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::QUEUE, $decision->action);
        $this->assertSame('processing-expired-requeued', $decision->reason);
    }

    public function testExpiredLoadedDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::LOADED, queuedAt: new DateTimeImmutable('@800'))
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::QUEUE, $decision->action);
        $this->assertSame('active-loaded-requeued', $decision->reason);
    }

    private function createVersion(): ResultFileVersion {
        return new ResultFileVersion(
            '/tmp/results/0001.game',
            sha1('/tmp/results/0001.game'),
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
        );
    }

    private function createScannerForDecision(
        ResultFileVersion       $version,
        ?ResultFileImportState  $state,
    ): ResultsDirectoryScanner {
        $versionFactory = $this
            ->getMockBuilder(ResultFileVersionFactory::class)
            ->onlyMethods(['fromFile'])
            ->getMock();
        $versionFactory
            ->expects($this->once())
            ->method('fromFile')
            ->with($version->path)
            ->willReturn($version);

        $stateRepository = $this
            ->getMockBuilder(ResultFileImportStateRepository::class)
            ->onlyMethods(['findByPathHash'])
            ->getMock();
        $stateRepository
            ->expects($this->once())
            ->method('findByPathHash')
            ->with($version->pathHash)
            ->willReturn($state);

        return new ResultsDirectoryScanner(
            $versionFactory,
            $stateRepository,
            $this->createScannerConfig(),
        );
    }

    private function isQueuedExpired(
        ResultsDirectoryScanner $scanner,
        ResultFileImportState   $state,
        DateTimeImmutable       $now,
    ): bool {
        $method = new ReflectionMethod(ResultsDirectoryScanner::class, 'isQueuedExpired');

        return $method->invoke($scanner, $state, $now);
    }

    private function createScannerConfig(): Config {
        return new class extends Config {
            public function __construct() {
                parent::__construct('/tmp');
            }

            public function getConfig(?string $category = null): array {
                $config = [
                    'ENV' => [
                        'RESULT_IMPORT_PROCESSING_TTL' => 300,
                        'RESULT_IMPORT_QUEUED_TTL' => 120,
                    ],
                ];

                return $category === null ? $config : ($config[$category] ?? []);
            }
        };
    }
}
