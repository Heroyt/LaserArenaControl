<?php

namespace App\Cron;

use App\Services\ResultsPrecacheService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;

/**
 *
 */
final readonly class GamesPrecacheJob implements Job
{
    public function __construct(
      private ResultsPrecacheService $precacheService,
      private Metrics                $metrics,
    ) {}

    /**
     * @param  JobLock  $lock
     *
     * @return void
     * @throws Throwable
     */
    public function run(JobLock $lock) : void {
        $this->metrics->add('cron_job_started', 1, ['game_precache']);
        // Lock should expire after all timeouts + 1 minute
        $lock->refresh(120.0);
        if ($this->precacheService->precacheNextGame()) {
            (new Logger(LOG_DIR, 'cron'))->debug('Precached game results');
        }
        $this->metrics->add('cron_job_ok', 1, ['game_precache']);
    }

    public function getName() : string {
        return 'Games Precache';
    }
}
