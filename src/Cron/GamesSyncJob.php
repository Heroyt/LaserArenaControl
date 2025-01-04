<?php

namespace App\Cron;

use App\Services\SyncService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;

/**
 *
 */
final readonly class GamesSyncJob implements Job
{
    public function __construct(
      private Metrics $metrics,
      public int      $limit = 5,
      public ?float   $timeout = null,
    ) {}

    /**
     * @param  JobLock  $lock
     *
     * @return void
     * @throws Throwable
     */
    public function run(JobLock $lock) : void {
        $this->metrics->add('cron_job_started', 1, ['game_sync']);
        // Lock should expire after all timeouts + 1 minute
        $lock->refresh($this->limit * ($this->timeout ?? 30.0) + 60.0);
        ob_start();
        SyncService::syncGames($this->limit, $this->timeout);
        (new Logger(LOG_DIR, 'cron'))->debug('Games sync done'.PHP_EOL.ob_get_clean());
        $this->metrics->add('cron_job_ok', 1, ['game_sync']);
    }

    public function getName() : string {
        return 'Games Sync';
    }
}
