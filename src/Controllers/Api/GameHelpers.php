<?php

namespace App\Controllers\Api;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use JsonException;
use Lsr\Core\Constants;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Throwable;

/**
 * Used for getting information about current games
 */
class GameHelpers extends ApiController
{

	/**
	 * @return never
	 * @throws JsonException
	 */
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
			if (isset($started) && ($now - $started->start?->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $started->fileTime) {
					continue;
				}
				$started->end = null;
				$started->finished = false;
				$game = $started;
				continue;
			}

			/** @var Game|null $loaded */
			$loaded = Info::get($system.'-game-loaded');
			if (isset($loaded) && ($now - $loaded->fileTime?->getTimestamp()) <= Constants::GAME_LOADED_TIME) {
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
				'loadTime'          => $game->fileTime?->getTimestamp(),
				'startTime'         => $game->start?->getTimestamp(),
				'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
				'playerCount' => $game->getPlayerCount(),
				'teamCount'         => count($game->getTeams()),
				'mode'        => $game->getMode(),
				'game' => $game,
			]
		);
	}

	/**
	 * @return never
	 * @throws JsonException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
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
				'loadTime'          => $game->fileTime?->getTimestamp(),
				'startTime'         => $game->start?->getTimestamp(),
				'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
				'playerCount'       => count($game->getPlayers()),
				'teamCount'         => count($game->getTeams()),
				'mode' => $game->getMode(),
			]
		);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function recalcSkill(Request $request) : never {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}

		try {
			$game->calculateSkills();
			$game->sync = false;
			$game->save();
			$game->sync();
		} catch (ModelNotFoundException|ValidationException $e) {
			$this->respond(['error' => 'Error while saving the player data', 'exception' => $e], 500);
		}

		$this->respond(['status' => 'OK']);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 * @throws GameModeNotFoundException
	 */
	public function changeGameMode(Request $request) : never {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}

		// Find game
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}

		// Find game mode
		$gameModeId = (int) ($request->post['mode'] ?? 0);
		if ($gameModeId < 1) {
			$this->respond(['error' => 'Invalid game mode ID'], 400);
		}
		$gameMode = GameModeFactory::getById($gameModeId, ['system' => $game::SYSTEM]);
		if (!isset($gameMode)) {
			$this->respond(['error' => 'Game mode not found'], 404);
		}

		$previousType = $game->gameType;

		// Set the new mode
		$game->gameType = $gameMode->type;
		$game->mode = $gameMode;

		// Check mode type change
		if ($previousType !== $game->getMode()) {
			if ($previousType === GameModeType::SOLO) {
				$this->respond(['error' => 'Cannot change mode from solo to team'], 400);
			}

			// Assign all players to one team
			$team = $game->getTeams()->first();
			if (!isset($team)) {
				$this->respond(['error' => 'Error while getting a team from a game'], 500);
			}
			/** @var Player $player */
			foreach ($game->getPlayers() as $player) {
				$player->setTeam($team);
			}
		}

		$game->recalculateScores();

		if (!$game->save()) {
			$game->sync();
			$this->respond(['error' => 'Error saving game'], 500);
		}

		$this->respond(['status' => 'OK']);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function recalcScores(Request $request) : never {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}

		$game->recalculateScores();
		if (!$game->save()) {
			$game->sync();
			$this->respond(['error' => 'Error saving game'], 500);
		}

		$this->respond(['status' => 'OK']);
	}

}