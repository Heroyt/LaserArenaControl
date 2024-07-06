<?php

namespace App\Cron;

use App\Services\ResultsPrecacheService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Throwable;

final readonly class GamesPrecacheJob implements Job
{
    public function __construct(private ResultsPrecacheService $precacheService) {
    }

    /**
     * @param JobLock $lock
     *
     * @return void
     * @throws Throwable
     */
    public function run(JobLock $lock): void {
        // Lock should expire after all timeouts + 1 minute
        $lock->refresh(120.0);
        if ($this->precacheService->precacheNextGame()) {
            (new Logger(LOG_DIR, 'cron'))->debug('Precached game results');
        }
    }

    public function getName(): string {
        return 'Games Precache';
    }
}
