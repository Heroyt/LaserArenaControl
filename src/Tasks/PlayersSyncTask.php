<?php

namespace App\Tasks;

use App\Services\LaserLiga\PlayerSynchronizationService;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

readonly class PlayersSyncTask implements TaskDispatcherInterface
{
    public function __construct(
      private PlayerSynchronizationService $synchronizationService,
    ) {}

    public static function getDiName() : string {
        return 'task.ligaPlayersSync';
    }

    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        try {
            $this->synchronizationService->syncAllLocalPlayers();
            $this->synchronizationService->syncAllArenaPlayers();
            $task->ack();
            return;
        } catch (Throwable $e) {
            $task->nack($e);
            return;
        }
    }
}
