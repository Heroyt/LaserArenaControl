<?php

namespace Tests\Unit;

use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use App\Services\ResultsDirectoryScanner;
use DateTimeImmutable;
use Lsr\Core\Config;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ResultsDirectoryScannerTest extends TestCase
{
    public function testProcessingStateExpiresAfterTtl(): void
    {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000')
            )
        );
    }

    private function createScanner(): ResultsDirectoryScanner
    {
        return new ResultsDirectoryScanner(
            new ResultFileVersionFactory(),
            $this->createStub(ResultFileImportStateRepository::class),
            new Config('/tmp')
        );
    }

    private function isProcessingExpired(
        ResultsDirectoryScanner $scanner,
        ResultFileImportState   $state,
        DateTimeImmutable       $now,
    ): bool
    {
        $method = new ReflectionMethod(ResultsDirectoryScanner::class, 'isProcessingExpired');

        return $method->invoke($scanner, $state, $now);
    }

    private function createState(
        ResultFileImportStatus $status,
        ?DateTimeImmutable     $processingStartedAt = null,
    ): ResultFileImportState
    {
        return new ResultFileImportState(
            id: 1,
            path: '/tmp/results/0001.game',
            pathHash: sha1('/tmp/results/0001.game'),
            system: 'evo6',
            seenMtime: 123,
            seenSize: 456,
            seenHash: str_repeat('a', 64),
            seenVersion: sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
            status: $status,
            attempts: 1,
            processingStartedAt: $processingStartedAt,
        );
    }

    public function testFreshProcessingStateDoesNotExpire(): void
    {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING, new DateTimeImmutable('@800')),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testProcessingStateWithoutStartTimeExpires(): void
    {
        $scanner = $this->createScanner();

        $this->assertTrue(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::PROCESSING),
                new DateTimeImmutable('@1000')
            )
        );
    }

    public function testNonProcessingStateDoesNotExpire(): void
    {
        $scanner = $this->createScanner();

        $this->assertFalse(
            $this->isProcessingExpired(
                $scanner,
                $this->createState(ResultFileImportStatus::QUEUED, new DateTimeImmutable('@600')),
                new DateTimeImmutable('@1000')
            )
        );
    }
}
