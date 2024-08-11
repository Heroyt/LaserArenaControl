<?php

namespace App\Tasks;

use App\Services\ImportService;
use App\Tasks\Payloads\GameImportPayload;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Requests\Dto\ErrorResponse;
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
    ) {
    }

    public static function getDiName(): string {
        return 'task.gamesImport';
    }

    /**
     * @param  ReceivedTaskInterface  $task
     * @return void
     * @throws JobsException
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function process(ReceivedTaskInterface $task): void {
        /** @var GameImportPayload $payload */
        $payload = igbinary_unserialize($task->getPayload());
        assert($payload->dir !== '', 'Import directory cannot be empty');

        $response = $this->importService->import($payload->dir);

        if ($response instanceof ErrorResponse) {
            $task->nack('Game import failed ' . $response->title);
            return;
        }

        $task->complete();
    }
}
