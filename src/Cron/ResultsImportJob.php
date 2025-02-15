<?php

namespace App\Cron;

use App\Models\System;
use App\Services\ImportService;
use Lsr\Core\Requests\Dto\ErrorResponse;
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
      private ImportService $importService,
      private Metrics       $metrics,
    ) {
        $this->logger = new Logger(LOG_DIR, 'cron');
    }

    public function getName() : string {
        return 'Import results';
    }

    public function run(JobLock $lock) : void {
        $this->metrics->add('cron_job_started', 1, ['results_import']);

        $lock->refresh(30.0);
        foreach (System::getActive() as $system) {
            $resultsDir = $system->resultsDir;
            if (!file_exists($resultsDir)) {
                continue;
            }

            $this->metrics->add('import_planned', 1, ['cron']);
            try {
                $response = $this->importService->import(DEFAULT_RESULTS_DIR);
            } catch (Throwable $e) {
                $this->logger->exception($e);
                $this->metrics->add('cron_job_error', 1, ['results_import']);
                continue;
            }

            if ($response instanceof ErrorResponse) {
                $this->logger->error(
                  $response->title.(!empty($response->detail) ? ' '.$response->detail : ''),
                  $response->values ?? []
                );
                if (isset($response->exception)) {
                    $this->logger->exception($response->exception);
                }
                $this->metrics->add('cron_job_error', 1, ['results_import']);
                continue;
            }
            if ($response->imported) {
                $this->logger->info('Imported '.$response->imported.'/'.$response->total.' results.');
            }
            $this->metrics->add('cron_job_ok', 1, ['results_import']);
        }
    }
}
