<?php

declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\ScanResultsDirectoryCommandHandler;
use App\DataObjects\Import\ResultsScanResult;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<ResultsScanResult>
 */
final readonly class ScanResultsDirectoryCommand implements CommandInterface
{
    /**
     * @param non-empty-string $dir
     */
    public function __construct(
        public string $dir,
        public bool   $all = false,
        public int    $limit = 0,
    )
    {
    }

    public function getHandler(): string
    {
        return ScanResultsDirectoryCommandHandler::class;
    }
}
