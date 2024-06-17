<?php

namespace App\Tasks;

use App\Core\App;
use App\Tasks\Payloads\MusicLoadPayload;
use App\Tools\GameLoading\LoaderInterface;
use App\Tools\GameLoading\MusicLoading;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

class MusicLoadTask implements TaskDispatcherInterface
{

    public function __construct() {}

    public static function getDiName() : string {
        return 'task.musicLoad';
    }

    public function process(ReceivedTaskInterface $task) : void {
        /** @var MusicLoadPayload $payload */
        $payload = igbinary_unserialize($task->getPayload());

        try {
            /** @var MusicLoading&LoaderInterface $loader */
            $loader = App::getService($payload->loader);
            $loader->loadMusic($payload->musicId, $payload->musicFile, $payload->system);
        } catch (Throwable $e) {
            $task->fail($e);
            return;
        }
        $task->fail('Error');
    }
}