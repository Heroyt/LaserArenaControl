<?php

namespace App\Tasks;

use App\Models\MusicMode;
use App\Services\FeatureConfig;
use App\Services\TaskProducer;
use App\Tasks\Payloads\MusicTrimPreviewPayload;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

class MusicTrimPreviewTask implements TaskDispatcherInterface
{

    public function __construct(
      private readonly FeatureConfig $config,
      private readonly TaskProducer  $taskProducer,
    ) {}

    public static function getDiName() : string {
        return 'task.musicTrimPreview';
    }

    public function process(ReceivedTaskInterface $task) : void {
        /** @var MusicTrimPreviewPayload $payload */
        $payload = igbinary_unserialize($task->getPayload());

        try {
            $music = MusicMode::get($payload->musicModeId);
        } catch (ModelNotFoundException | ValidationException $e) {
            $task->fail($e);
            return;
        }

        $music->trimMediaToPreview();

        if ($this->config->isFeatureEnabled('liga')) {
            try {
                $this->taskProducer->push(MusicSyncTask::class, $music, new Options(priority: 99));
            } catch (JobsException) {
            }
        }

        $task->complete();
    }
}