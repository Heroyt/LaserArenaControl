<?php

namespace App\Tasks;

use App\Models\MusicMode;
use App\Services\FeatureConfig;
use App\Tasks\Payloads\MusicSyncPayload;
use App\Tasks\Payloads\MusicTrimPreviewPayload;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Lsr\Roadrunner\Tasks\TaskProducer;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

readonly class MusicTrimPreviewTask implements TaskDispatcherInterface
{
    public function __construct(
      private FeatureConfig $config,
      private TaskProducer  $taskProducer,
    ) {}

    public static function getDiName() : string {
        return 'task.musicTrimPreview';
    }

    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if ($payload === null) {
            $task->nack('Missing payload');
            return;
        }
        if (!($payload instanceof MusicTrimPreviewPayload)) {
            $task->nack('Invalid payload');
            return;
        }

        try {
            $music = MusicMode::get($payload->musicModeId);
        } catch (ModelNotFoundException | ValidationException $e) {
            $task->nack($e);
            return;
        }

        $music->trimMediaToPreview();

        if ($this->config->isFeatureEnabled('liga')) {
            try {
                $this->taskProducer->push(
                  MusicSyncTask::class,
                  new MusicSyncPayload($music),
                  new Options(priority: 99)
                );
            } catch (JobsException) {
            }
        }

        $task->ack();
    }
}
