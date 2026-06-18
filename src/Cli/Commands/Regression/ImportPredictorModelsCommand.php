<?php

declare(strict_types=1);

namespace App\Cli\Commands\Regression;

use App\Services\Predictor\PredictorModelImportService;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ImportPredictorModelsCommand extends Command
{
    public function __construct(
        private readonly PredictorModelImportService $importService,
    ) {
        parent::__construct('predictor:models:import');
    }

    public static function getDefaultName(): string {
        return 'predictor:models:import';
    }

    public static function getDefaultDescription(): string {
        return 'Import predictor model JSON artifacts into the local database.';
    }

    protected function configure(): void {
        $this->addArgument('path', InputArgument::REQUIRED, 'Manifest JSON or single model artifact JSON path.');
        $this->addOption('activate', null, InputOption::VALUE_NONE, 'Mark imported models active immediately.');
        $this->addOption('created-by', null, InputOption::VALUE_REQUIRED, 'Optional importer identifier.');
        $this->addOption('notes', null, InputOption::VALUE_REQUIRED, 'Optional import notes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $path = $input->getArgument('path');
        if ( ! is_string($path) || $path === '') {
            throw new RuntimeException('Import path must be a non-empty string.');
        }

        $createdBy = $input->getOption('created-by');
        $notes = $input->getOption('notes');

        try {
            $result = $this->importService->importPath(
                $path,
                activate: $input->getOption('activate') === true,
                createdBy: is_string($createdBy) && $createdBy !== '' ? $createdBy : null,
                notes: is_string($notes) && $notes !== '' ? $notes : null,
            );
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Imported predictor models: %d new, %d updated, %d skipped, %d active.</info>',
            $result->imported,
            $result->updated,
            $result->skipped,
            $result->activated,
        ));

        return self::SUCCESS;
    }
}
