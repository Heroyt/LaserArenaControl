<?php

namespace App\Tasks;

use App\Services\LaserLiga\LigaApi;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

readonly class MusicSyncTask implements TaskDispatcherInterface
{
    public function __construct(
      private LigaApi $api,
    ) {}

    public static function getDiName() : string {
        return 'task.musicSync';
    }

    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        try {
            if ($this->api->syncMusicModes()) {
                $task->ack();
                return;
            }
        } catch (ValidationException $e) {
            $task->nack($e);
            return;
        }
        $task->nack('Error');
    }
}
