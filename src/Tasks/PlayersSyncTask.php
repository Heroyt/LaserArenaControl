<?php

namespace App\Tasks;

use App\Services\LaserLiga\PlayerSynchronizationService;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

class PlayersSyncTask implements TaskDispatcherInterface
{
    public function __construct(
      private readonly PlayerSynchronizationService $synchronizationService,
    ) {}

    public static function getDiName() : string {
        return 'task.ligaPlayersSync';
    }

    public function process(ReceivedTaskInterface $task) : void {
        try {
            $this->synchronizationService->syncAllLocalPlayers();
            $this->synchronizationService->syncAllArenaPlayers();
            $task->complete();
            return;
        } catch (Throwable $e) {
            $task->fail($e);
            return;
        }
    }
}
