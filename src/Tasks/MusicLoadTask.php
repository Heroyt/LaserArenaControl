<?php

namespace App\Tasks;

use App\Core\App;
use App\Tasks\Payloads\MusicLoadPayload;
use App\Tools\GameLoading\LoaderInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

/**
 *
 */
class MusicLoadTask implements TaskDispatcherInterface
{
    public function __construct() {
    }

    public static function getDiName(): string {
        return 'task.musicLoad';
    }

    public function process(ReceivedTaskInterface $task): void {
        /** @var MusicLoadPayload $payload */
        $payload = igbinary_unserialize($task->getPayload());

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
