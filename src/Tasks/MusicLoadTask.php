<?php

namespace App\Tasks;

use App\Services\LigaApi;
use App\Tasks\Payloads\MusicLoadPayload;
use Lsr\Core\Exceptions\ValidationException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

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