<?php

namespace App\Tasks;

use App\GameModels\Factory\GameFactory;
use App\Services\GameHighlight\GameHighlightService;
use App\Tasks\Payloads\GameHighlightsPayload;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 *
 */
readonly class GameHighlightsTask implements TaskDispatcherInterface
{
    public function __construct(
      private GameHighlightService $highlightService
    ) {}

    public static function getDiName() : string {
        return 'task.gamesHighlights';
    }

    /**
     * @throws JobsException
     */
    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if ($payload === null) {
            $task->nack('Missing payload');
            return;
        }
        if (!($payload instanceof GameHighlightsPayload)) {
            $task->nack('Invalid payload');
            return;
        }
        if (!isset($payload->code)) {
            $task->nack('Missing game code in payload');
            return;
        }
        echo 'Loading game highlights: '.$payload->code;
        $game = GameFactory::getByCode($payload->code);
        if (!isset($game)) {
            $task->nack('Game not found');
            return;
        }
        $highlights = $this->highlightService->getHighlightsForGame($game, false);
        if ($highlights->count() === 0) {
            echo 'No highlights for this game';
        }
        else {
            echo 'Loaded '.$highlights->count().' highlights';
        }
        $task->ack();
    }
}
