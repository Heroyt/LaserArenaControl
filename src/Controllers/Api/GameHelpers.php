<?php

namespace App\Controllers\Api;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Used for getting information about current games
 */
class GameHelpers extends ApiController
{
    public function __construct(
      private readonly Config $config,
    ) {
        parent::__construct();
    }

    /**
     * @return never
     * @throws JsonException
     */
    public function getLoadedGameInfo(Request $request) : ResponseInterface {
        // Allow for filtering games just from one system
        $system = $request->getGet('system', 'all');
        $systems = [$system];

        // Fallback to all available systems
        if ($system === 'all') {
            $systems = GameFactory::getSupportedSystems();
        }

        $now = time();

        /** @var Game|null $game */
        $game = null;
        $allGames = [];

        $gameLoadedTime = (int) ($this->config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $gameStartedTime = (int) ($this->config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);

        // Try to find the last loaded or started games in selected systems
        foreach ($systems as $system) {
            /** @var Game|null $started */
            $started = Info::get($system.'-game-started');
            $allGames['started'] = $started;
            if (isset($started) && ($now - $started->start?->getTimestamp()) <= $gameStartedTime) {
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
            $allGames['loaded'] = $loaded;
            if (isset($loaded) && ($now - $loaded->fileTime?->getTimestamp()) <= $gameLoadedTime) {
                if (isset($this->game) && $this->game->fileTime > $loaded->fileTime) {
                    continue;
                }
                $game = $loaded;
            }
        }

        if (!isset($game)) {
            return $this->respond(['error' => 'No game found', 'games' => $allGames], 404);
        }

        return $this->respond(
          [
            'currentServerTime' => time(),
            'started'           => $game->started,
            'finished'          => $game->finished,
            'loadTime'          => $game->fileTime?->getTimestamp(),
            'startTime'         => $game->start?->getTimestamp(),
            'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
            'playerCount' => $game->playerCount,
            'teamCount'   => count($game->teams),
            'mode'        => $game->mode,
            'game'              => $game,
          ]
        );
    }

    /**
     * @return never
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getGateGameInfo() : ResponseInterface {
        /** @var Game|null $game */
        $game = Info::get('gate-game');

        if (!isset($game)) {
            return $this->respond(['error' => 'No game found'], 404);
        }

        return $this->respond(
          [
            'currentServerTime' => time(),
            'gateTime'          => Info::get('gate-time'),
            'started'           => $game->started,
            'finished'          => $game->finished,
            'loadTime'          => $game->fileTime?->getTimestamp(),
            'startTime'         => $game->start?->getTimestamp(),
            'gameLength'        => !isset($game->timing) ? 0 : ($game->timing->gameLength * 60),
            'playerCount' => count($game->players),
            'teamCount'   => count($game->teams),
            'mode'        => $game->mode,
          ]
        );
    }

    /**
     * @param  Request  $request
     *
     * @return never
     * @throws JsonException
     * @throws Throwable
     */
    public function recalcSkill(Request $request) : ResponseInterface {
        $code = $request->params['code'] ?? '';
        if (empty($code)) {
            return $this->respond(['error' => 'Invalid code'], 400);
        }
        $game = GameFactory::getByCode($code);
        if (!isset($game)) {
            return $this->respond(['error' => 'Game not found'], 404);
        }

        try {
            $game->calculateSkills();
            $game->sync = false;
            $game->save();
            $game->sync();
        } catch (ModelNotFoundException | ValidationException $e) {
            return $this->respond(['error' => 'Error while saving the player data', 'exception' => $e], 500);
        }

        return $this->respond(['status' => 'OK']);
    }

    /**
     * @param  Request  $request
     *
     * @return never
     * @throws JsonException
     * @throws Throwable
     * @throws GameModeNotFoundException
     */
    public function changeGameMode(Request $request) : ResponseInterface {
        $code = $request->params['code'] ?? '';
        if (empty($code)) {
            return $this->respond(['error' => 'Invalid code'], 400);
        }

        // Find game
        $game = GameFactory::getByCode($code);
        if (!isset($game)) {
            return $this->respond(['error' => 'Game not found'], 404);
        }

        // Find game mode
        $gameModeId = (int) $request->getPost('mode', 0);
        if ($gameModeId < 1) {
            return $this->respond(['error' => 'Invalid game mode ID'], 400);
        }
        $gameMode = GameModeFactory::getById($gameModeId, ['system' => $game::SYSTEM]);
        if (!isset($gameMode)) {
            return $this->respond(['error' => 'Game mode not found'], 404);
        }

        $previousType = $game->gameType;

        // Set the new mode
        $game->gameType = $gameMode->type;
        $game->mode = $gameMode;

        // Check mode type change
        if ($previousType !== $game->mode) {
            if ($previousType === GameModeType::SOLO) {
                return $this->respond(['error' => 'Cannot change mode from solo to team'], 400);
            }

            // Assign all players to one team
            $team = $game->teams->first();
            if (!isset($team)) {
                return $this->respond(['error' => 'Error while getting a team from a game'], 500);
            }
            /** @var Player $player */
            foreach ($game->players as $player) {
                $player->team = $team;
            }
        }

        $game->recalculateScores();

        if (!$game->save()) {
            $game->sync();
            return $this->respond(['error' => 'Error saving game'], 500);
        }

        return $this->respond(['status' => 'OK']);
    }

    /**
     * @param  Request  $request
     *
     * @return never
     * @throws JsonException
     * @throws Throwable
     */
    public function recalcScores(Request $request) : ResponseInterface {
        $code = $request->params['code'] ?? '';
        if (empty($code)) {
            return $this->respond(['error' => 'Invalid code'], 400);
        }
        $game = GameFactory::getByCode($code);
        if (!isset($game)) {
            return $this->respond(['error' => 'Game not found'], 404);
        }

        $game->recalculateScores();
        if (!$game->save()) {
            $game->sync();
            return $this->respond(['error' => 'Error saving game'], 500);
        }

        return $this->respond(['status' => 'OK']);
    }
}
