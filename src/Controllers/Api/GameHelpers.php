<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Constants;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;

/**
 * Used for getting information about current games
 */
class GameHelpers extends ApiController
{

	public function getLoadedGameInfo() : never {
		// Allow for filtering games just from one system
		$system = $_GET['system'] ?? 'all';
		$systems = [$system];

		// Fallback to all available systems
		if ($system === 'all') {
			$systems = GameFactory::getSupportedSystems();
		}

		$now = time();

		/** @var Game|null $game */
		$game = null;

		// Try to find the last loaded or started games in selected systems
		foreach ($systems as $system) {
			/** @var Game|null $started */
			$started = Info::get($system.'-game-started');
			if (isset($started) && ($now - $started->start->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $started->fileTime) {
					continue;
				}
				$game = $started;
				continue;
			}

			/** @var Game|null $loaded */
			$loaded = Info::get($system.'-game-loaded');
			if (isset($loaded) && ($now - $loaded->fileTime->getTimestamp()) <= Constants::GAME_LOADED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $loaded->fileTime) {
					continue;
				}
				$game = $loaded;
			}
		}

		if (!isset($game)) {
			$this->respond(['error' => 'No game found'], 404);
		}

		$this->respond(
			[
				'currentServerTime' => time(),
				'started'           => $game->started,
				'finished'          => $game->finished,
				'loadTime'          => $game->fileTime->getTimestamp(),
				'startTime'         => $game->start?->getTimestamp(),
				'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
				'playerCount'       => $game->playerCount,
				'teamCount'         => count($game->getTeams()),
				'mode'              => $game->mode,
			]
		);
	}

	public function getGateGameInfo() : never {
		/** @var Game|null $game */
		$game = Info::get('gate-game');

		if (!isset($game)) {
			$this->respond(['error' => 'No game found'], 404);
		}

		$this->respond(
			[
				'currentServerTime' => time(),
				'gateTime'          => Info::get('gate-time'),
				'started'           => $game->started,
				'finished'          => $game->finished,
				'loadTime'          => $game->fileTime->getTimestamp(),
				'startTime'         => $game->start?->getTimestamp(),
				'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
				'playerCount'       => $game->playerCount,
				'teamCount'         => count($game->getTeams()),
				'mode'              => $game->mode,
			]
		);
	}

}