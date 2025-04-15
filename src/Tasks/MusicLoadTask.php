<?php

namespace App\Tasks;

use App\Core\App;
use App\Tasks\Payloads\MusicLoadPayload;
use App\Tools\GameLoading\LoaderInterface;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

/**
 *
 */
readonly class MusicLoadTask implements TaskDispatcherInterface
{
    public static function getDiName() : string {
        return 'task.musicLoad';
    }

    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if ($payload === null) {
            $task->nack('Missing payload');
            return;
        }
        if (!($payload instanceof MusicLoadPayload)) {
            $task->nack('Invalid payload');
            return;
        }

        try {
            $loader = App::getService($payload->loader);
            assert($loader instanceof LoaderInterface, 'Loader must be instance of LoaderInterface');
            $loader->loadMusic($payload->musicId, $payload->musicFile, $payload->system, $payload->timeSinceStart);
        } catch (Throwable $e) {
            $task->nack($e);
            return;
        }
        $task->nack('Error');
    }
}
