<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\DataObjects\Import\ResultsScanResult;
use App\Services\ResultsDirectoryScanner;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

readonly class ScanResultsDirectoryCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ResultsDirectoryScanner $scanner,
    )
    {
    }

    /**
     * @param ScanResultsDirectoryCommand $command
     */
    public function handle(CommandInterface $command): ResultsScanResult
    {
        return $this->scanner->scan($command->dir, $command->all, $command->limit);
    }
}
