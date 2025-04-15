<?php

namespace App\Tasks;

use App\Services\ImportService;
use App\Tasks\Payloads\GameImportPayload;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Throwable;

/**
 *
 */
readonly class GameImportTask implements TaskDispatcherInterface
{
    public const int PRIORITY = 20;

    public function __construct(
      private ImportService $importService
    ) {}

    public static function getDiName() : string {
        return 'task.gamesImport';
    }

    /**
     * @param  ReceivedTaskInterface  $task
     * @param  TaskPayloadInterface|null  $payload
     * @return void
     * @throws JobsException
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if ($payload === null) {
            $task->nack('Missing payload');
            return;
        }
        if (!($payload instanceof GameImportPayload) || $payload->dir === '') {
            $task->nack('Invalid payload');
            return;
        }

        $response = $this->importService->import($payload->dir);

        if ($response instanceof ErrorResponse) {
            $task->nack('Game import failed '.$response->title);
            return;
        }

        $task->complete();
    }
}
