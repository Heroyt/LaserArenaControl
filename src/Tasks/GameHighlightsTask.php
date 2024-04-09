<?php

namespace App\Tasks;

use App\GameModels\Factory\GameFactory;
use App\Services\GameHighlight\GameHighlightService;
use App\Tasks\Payloads\GameHighlightsPayload;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 *
 */
class GameHighlightsTask implements TaskDispatcherInterface
{

	public function __construct(
		private GameHighlightService $highlightService
	) {}

	public static function getDiName() : string {
		return 'task.gamesHighlights';
	}

	/**
	 * @param ReceivedTaskInterface $task
	 * @return void
	 * @throws JobsException
	 */
	public function process(ReceivedTaskInterface $task) : void {
		/** @var GameHighlightsPayload $payload */
		$payload = igbinary_unserialize($task->getPayload());

		if (!isset($payload->code)) {
			$task->fail('Missing game code in payload');
			return;
		}
		echo 'Loading game highlights: '.$payload->code;
		$game = GameFactory::getByCode($payload->code);
		if (!isset($game)) {
			$task->fail('Game not found');
			return;
		}
		$highlights = $this->highlightService->getHighlightsForGame($game, false);
		if ($highlights->count() === 0) {
			echo 'No highlights for this game';
		}
		else {
			echo 'Loaded '.$highlights->count().' highlights';
		}
		$task->complete();
	}
}