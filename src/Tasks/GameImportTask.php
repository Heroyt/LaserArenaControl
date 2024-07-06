<?php

namespace App\Tasks;

use App\Api\Response\ErrorDto;
use App\Services\ImportService;
use App\Tasks\Payloads\GameImportPayload;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 *
 */
class GameImportTask implements TaskDispatcherInterface
{
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
     */
    public function process(ReceivedTaskInterface $task): void {
        /** @var GameImportPayload $payload */
        $payload = igbinary_unserialize($task->getPayload());

        $response = $this->importService->import($payload->dir);

        if ($response instanceof ErrorDto) {
            $task->fail('Game import failed ' . $response->title);
            return;
        }

        $task->complete();
    }
}
