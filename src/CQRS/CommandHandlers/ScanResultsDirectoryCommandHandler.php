<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\ImportResultFileCommand;
use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\DataObjects\Import\ResultsScanResult;
use App\Services\ResultsDirectoryScanner;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

readonly class ScanResultsDirectoryCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ResultsDirectoryScanner $scanner,
        private CommandBus $commandBus,
    )
    {
    }

    /**
     * @param ScanResultsDirectoryCommand $command
     */
    public function handle(CommandInterface $command): ResultsScanResult
    {
        $result = $this->scanner->scan(
            $command->dir,
            $command->all,
            $command->limit,
            $command->includeContent,
            $command->maxContentBytes,
        );
        if ($command->queueImports) {
            foreach ($result->queuedFiles as $queuedFile) {
                $this->commandBus->dispatchAsync(ImportResultFileCommand::fromQueuedFile($queuedFile));
            }
        }

        return $result;
    }
}
