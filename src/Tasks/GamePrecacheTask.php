<?php

namespace App\Tasks;

use App\Services\ResultsPrecacheService;
use App\Tasks\Payloads\GamePrecachePayload;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

/**
 *
 */
class GamePrecacheTask implements TaskDispatcherInterface
{

	public function __construct(
		private ResultsPrecacheService $precacheService
	) {}

	public static function getDiName() : string {
		return 'task.gamesPrecache';
	}

	/**
	 * @param ReceivedTaskInterface $task
	 * @return void
	 * @throws JobsException
	 */
	public function process(ReceivedTaskInterface $task) : void {
		/** @var GamePrecachePayload $payload */
		$payload = igbinary_unserialize($task->getPayload());

		if (isset($payload->code)) {
			echo 'Precaching game: '.$payload->code;
			if ($this->precacheService->precacheGameByCode($payload->code, $payload->style, $payload->template)) {
				$task->complete();
			}
			else {
				$task->fail('Game precache failed');
			}
			return;
		}

		if ($this->precacheService->precacheNextGame($payload->style, $payload->template)) {
			$task->complete();
		}
		else {
			$task->fail('Game precache failed');
		}
	}
}