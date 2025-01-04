<?php

namespace App\Tasks;

use App\Services\LaserLiga\LigaApi;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

class MusicSyncTask implements TaskDispatcherInterface
{
    public function __construct(
      private readonly LigaApi $api,
    ) {}

    public static function getDiName() : string {
        return 'task.musicSync';
    }

    public function process(ReceivedTaskInterface $task) : void {
        try {
            if ($this->api->syncMusicModes()) {
                $task->complete();
                return;
            }
        } catch (ValidationException $e) {
            $task->fail($e);
            return;
        }
        $task->fail('Error');
    }
}
