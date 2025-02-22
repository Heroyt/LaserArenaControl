<?php

namespace App\Tasks;

use App\Services\ResultsPrecacheService;
use App\Tasks\Payloads\GamePrecachePayload;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 *
 */
readonly class GamePrecacheTask implements TaskDispatcherInterface
{
    public function __construct(
      private ResultsPrecacheService $precacheService
    ) {}

    public static function getDiName() : string {
        return 'task.gamesPrecache';
    }

    /**
     * @param  ReceivedTaskInterface  $task
     * @return void
     * @throws JobsException
     */
    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if ($payload === null) {
            $task->nack('Missing payload');
            return;
        }
        if (!($payload instanceof GamePrecachePayload)) {
            $task->nack('Invalid payload');
            return;
        }

        if (isset($payload->code)) {
            echo 'Precaching game: '.$payload->code;
            if ($this->precacheService->precacheGameByCode($payload->code, $payload->style, $payload->template)) {
                $task->ack();
            }
            else {
                $task->nack('Game precache failed');
            }
            return;
        }

        if ($this->precacheService->precacheNextGame($payload->style, $payload->template)) {
            $task->ack();
            return;
        }
        $task->nack('Game precache failed');
    }
}
