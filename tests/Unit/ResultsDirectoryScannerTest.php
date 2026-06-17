<?php

namespace Tests\Unit;

use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileScanAction;
use App\DataObjects\Import\ResultFileVersion;
use App\GameModels\Factory\GameFactory;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use App\Services\ResultsDirectoryScanner;
use DateTimeImmutable;
use Lsr\Core\Config;
use Lsr\LaserLiga\PlayerProviderInterface;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Lg\Results\Interface\GameModeProviderInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface as ParsedGameInterface;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

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
                $result->errors[0]->message,
            );
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }

    public function testScanLimitDoesNotClassifyBeyondLimitCandidateAsInvalid(): void {
        $this->installScannerTestParser(acceptsFiles: true);
        $dir = sys_get_temp_dir() . '/lac-results-scanner-' . uniqid('', true);
        mkdir($dir);
        $firstFile = $dir . '/0001.game';
        $secondFile = $dir . '/0002.game';
        file_put_contents($firstFile, 'SITE{EVO-6 MAXX}#');
        file_put_contents($secondFile, 'SITE{EVO-6 MAXX}#');

        $stateRepository = $this
            ->getMockBuilder(ResultFileImportStateRepository::class)
            ->onlyMethods(['findByPathHash', 'saveSeen'])
            ->getMock();
        $stateRepository->expects($this->once())->method('findByPathHash')->willReturn(null);
        $stateRepository->expects($this->once())->method('saveSeen');

        try {
            $result = new ResultsDirectoryScanner(
                new ResultFileVersionFactory(),
                $stateRepository,
                $this->createScannerConfig(),
            )->scan($dir, limit: 1);

            $this->assertSame(1, $result->seen);
            $this->assertSame(1, $result->queued);
            $this->assertSame(0, $result->invalid);
            $this->assertCount(1, $result->decisions);
        } finally {
            unlink($firstFile);
            unlink($secondFile);
            rmdir($dir);
        }
    }

    public function testScanMetadataFailureEmitsInvalidDecision(): void {
        $this->installScannerTestParser(acceptsFiles: true);
        $dir = sys_get_temp_dir() . '/lac-results-scanner-' . uniqid('', true);
        mkdir($dir);
        $file = $dir . '/0001.game';
        file_put_contents($file, 'SITE{EVO-6 MAXX}#');

        $versionFactory = $this
            ->getMockBuilder(ResultFileVersionFactory::class)
            ->onlyMethods(['fromFile'])
            ->getMock();
        $versionFactory
            ->expects($this->once())
            ->method('fromFile')
            ->with($file)
            ->willThrowException(new RuntimeException('metadata failed'));

        try {
            $result = new ResultsDirectoryScanner(
                $versionFactory,
                $this->createStub(ResultFileImportStateRepository::class),
                $this->createScannerConfig(),
            )->scan($dir);

            $this->assertSame(0, $result->seen);
            $this->assertSame(0, $result->queued);
            $this->assertSame(1, $result->invalid);
            $this->assertCount(1, $result->decisions);
            $this->assertSame(ResultFileScanAction::INVALID, $result->decisions[0]->action);
            $this->assertSame('invalid-metadata-error', $result->decisions[0]->reason);
            $this->assertSame('metadata failed', $result->decisions[0]->lastError);
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }

    public function testDescribeDecisionRejectsPreparedLoadFile(): void {
        $version = new ResultFileVersion(
            '/tmp/results/0000.game',
            sha1('/tmp/results/0000.game'),
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0000.game:123:456:' . str_repeat('a', 64)),
        );
        $scanner = $this->createScannerForDecision($version, null);

        $decision = $scanner->describeFileDecision($version->path);

        $this->assertSame(ResultFileScanAction::INVALID, $decision->action);
        $this->assertSame('invalid-prepared-load-file', $decision->reason);
    }

    public function testDescribeDecisionRejectsFileWithoutAcceptedParser(): void {
        $this->installScannerTestParser(acceptsFiles: false);
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision($version, null, installParser: false);

        $decision = $scanner->describeFileDecision($version->path);

        $this->assertSame(ResultFileScanAction::INVALID, $decision->action);
        $this->assertSame('invalid-no-parser', $decision->reason);
    }

    public function testProcessingStateExpiresAfterTtl(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000'),
            ),
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
                new DateTimeImmutable('@1000'),
            ),
        );
    }

    public function testProcessingStateWithoutStartTimeExpires(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING),
                new DateTimeImmutable('@1000'),
            ),
        );
    }

    public function testNonProcessingStateDoesNotExpire(): void {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::QUEUED, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000'),
            ),
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
                new DateTimeImmutable('@1000'),
            ),
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
                new DateTimeImmutable('@1000'),
            ),
        );
    }

    public function testSeenStateWithoutQueueTimeExpiresWhenUnprocessed(): void {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isQueuedExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::SEEN),
                new DateTimeImmutable('@1000'),
            ),
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
                new DateTimeImmutable('@1000'),
            ),
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
                new DateTimeImmutable('@1000'),
            ),
        );
    }

    public function testImportedSameVersionDecisionIsUnchangedImported(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(
                ResultFileImportStatus::IMPORTED,
                processedVersion: $version->version,
            ),
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::SKIP, $decision->action);
        $this->assertSame('unchanged-imported', $decision->reason);
    }

    public function testFreshQueuedDecisionIsQueuedFresh(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::QUEUED, queuedAt: new DateTimeImmutable('@950')),
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::SKIP, $decision->action);
        $this->assertSame('queued-fresh', $decision->reason);
    }

    public function testExpiredQueuedDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::QUEUED, queuedAt: new DateTimeImmutable('@800')),
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::QUEUE, $decision->action);
        $this->assertSame('queued-expired-requeued', $decision->reason);
    }

    public function testExpiredProcessingDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@600')),
        );

        $decision = $scanner->describeFileDecision($version->path, now: new DateTimeImmutable('@1000'));

        $this->assertSame(ResultFileScanAction::QUEUE, $decision->action);
        $this->assertSame('processing-expired-requeued', $decision->reason);
    }

    public function testExpiredLoadedDecisionIsRequeued(): void {
        $version = $this->createVersion();
        $scanner = $this->createScannerForDecision(
            $version,
            $this->createState(ResultFileImportStatus::LOADED, queuedAt: new DateTimeImmutable('@800')),
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
        bool                    $installParser = true,
    ): ResultsDirectoryScanner {
        if ($installParser) {
            $this->installScannerTestParser(acceptsFiles: true);
        }

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

    private function installScannerTestParser(bool $acceptsFiles): void {
        new ReflectionProperty(GameFactory::class, 'supportedSystems')
            ->setValue(null, ['evo6']);
        new ReflectionProperty(\Lsr\Core\App::class, 'container')
            ->setValue(null, new ScannerTestContainer(new ScannerTestParser(
                $this->createStub(PlayerProviderInterface::class),
                $this->createStub(GameModeProviderInterface::class),
                $acceptsFiles,
            )));
    }
}

final class ScannerTestContainer extends Container
{
    public function __construct(ScannerTestParser $parser) {
        parent::__construct();
        $this->addService('result.parser.evo6', $parser);
    }
}

/**
 * @extends AbstractResultsParser<ParsedGameInterface<\App\GameModels\Game\Lasermaxx\Evo6\Team, \App\GameModels\Game\Lasermaxx\Evo6\Player, array<string, mixed>>>
 */
final class ScannerTestParser extends AbstractResultsParser
{
    public static bool $acceptsFiles = true;

    public function __construct(
        PlayerProviderInterface $playerProvider,
        GameModeProviderInterface $gameModeProvider,
        bool $acceptsFiles,
    ) {
        unset($playerProvider, $gameModeProvider);
        self::$acceptsFiles = $acceptsFiles;
    }

    public static function getFileGlob(): string {
        return '*.game';
    }

    public static function checkFile(string $fileName = '', string $contents = ''): bool {
        unset($fileName, $contents);
        return self::$acceptsFiles;
    }

    /**
     * @return ParsedGameInterface<\App\GameModels\Game\Lasermaxx\Evo6\Team, \App\GameModels\Game\Lasermaxx\Evo6\Player, array<string, mixed>>
     */
    public function parse(): ParsedGameInterface {
        throw new RuntimeException('Scanner tests do not parse result files.');
    }

    /**
     * @param ParsedGameInterface<\App\GameModels\Game\Lasermaxx\Evo6\Team, \App\GameModels\Game\Lasermaxx\Evo6\Player, array<string, mixed>> $game
     * @param array<string, mixed> $meta
     */
    protected function processExtensions(ParsedGameInterface $game, array $meta): void {
    }
}
