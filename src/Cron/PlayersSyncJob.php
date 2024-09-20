<?php

namespace App\Cron;

use App\Services\LaserLiga\PlayerSynchronizationService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 *
 */
final readonly class PlayersSyncJob implements Job
{
    public function __construct(
        private PlayerSynchronizationService $synchronizationService,
        private Metrics $metrics,
    ) {
    }

    public function run(JobLock $lock): void {
        $this->metrics->add('cron_job_started', 1, ['liga_players_sync']);
        $lock->refresh(120.0);
        $this->synchronizationService->syncAllLocalPlayers();
        $this->synchronizationService->syncAllArenaPlayers();
        (new Logger(LOG_DIR, 'cron'))->debug('Liga players sync done');
        $this->metrics->add('cron_job_ok', 1, ['liga_players_sync']);
    }

    public function getName(): string {
        return 'Vest sync';
    }
}
