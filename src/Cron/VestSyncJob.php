<?php

namespace App\Cron;

use App\Services\LigaApi;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 *
 */
final readonly class VestSyncJob implements Job
{
    public function __construct(
        private LigaApi $api,
        private Metrics $metrics,
    ) {
    }

    public function run(JobLock $lock): void {
        $this->metrics->add('cron_job_started', 1, ['vest_sync']);
        $lock->refresh(120.0);
        if ($this->api->syncVests()) {
            (new Logger(LOG_DIR, 'cron'))->debug('Vest sync successful');
            $this->metrics->add('cron_job_ok', 1, ['vest_sync']);
            return;
        }
        (new Logger(LOG_DIR, 'cron'))->warning('Vest sync failed');
        $this->metrics->add('cron_job_error', 1, ['vest_sync']);
    }

    public function getName(): string {
        return 'Vest sync';
    }
}
