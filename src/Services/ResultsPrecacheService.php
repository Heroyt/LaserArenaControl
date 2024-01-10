<?php

namespace App\Services;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\PrintStyle;
use Redis;
use Throwable;

readonly class ResultsPrecacheService
{

	public const KEY = 'result-precache-queue';

	public function __construct(
		private Redis             $redis,
		private ResultPrintService $printService
	) {
	}

	/**
	 * Put game codes into a precache queue
	 *
	 * @param string ...$codes
	 *
	 * @return int|false
	 */
	public function prepareGamePrecache(string ...$codes): int|false {
		return $this->redis->lPush($this::KEY, ...$codes);
	}

	/**
	 * Precache next game results in precache queue
	 *
	 * @return bool True if a game is precached, false on error or if there is no game to precache
	 */
	public function precacheNextGame(): bool {
		$code = $this->redis->lPop($this::KEY);
		if (empty($code)) {
			return false;
		}

		try {
			$game = GameFactory::getByCode($code);
		} catch (Throwable) {
		}
		if (!isset($game)) {
			return false;
		}

		// Precache default styles
		$style = PrintStyle::getActiveStyleId();
		/** @var string $template */
		$template = Info::get('default_print_template', 'default');
		$file = $this->printService->getResultsPdf($game, $style, $template, 1);
		return $file !== '';
	}

}