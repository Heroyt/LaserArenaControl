<?php

declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\Models\System;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;

/**
 * Fallback results import. Results should be imported automatically using the API.
 */
final readonly class ResultsImportJob implements Job
{
    private Logger $logger;

    public function __construct(
        private CommandBus $commandBus,
        private Metrics    $metrics,
    ) {
        $this->logger = new Logger(LOG_DIR, 'cron');
    }

    public function getName(): string {
        return 'Import results';
    }

    public function run(JobLock $lock): void {
        $this->metrics->add('cron_job_started', 1, ['results_import']);

        $lock->refresh(30.0);
        foreach (System::getActive(false) as $system) {
            $resultsDir = $system->resultsDir;
            if (empty($resultsDir) || ! file_exists($resultsDir)) {
                continue;
            }

            $this->metrics->add('import_planned', 1, ['cron']);
            try {
                $response = $this->commandBus->dispatch(new ScanResultsDirectoryCommand($resultsDir));
            } catch (Throwable $e) {
                $this->logger->exception($e);
                $this->metrics->add('cron_job_error', 1, ['results_import']);
                continue;
            }

            if ($response->queued > 0 || $response->errors !== []) {
                $this->logger->info(
                    'Queued ' . $response->queued . '/' . $response->seen . ' changed result files for import.',
                );
            }
            $this->metrics->add('cron_job_ok', 1, ['results_import']);
        }
    }
}
