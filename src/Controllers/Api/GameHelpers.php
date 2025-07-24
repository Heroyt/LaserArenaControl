<?php

namespace App\Controllers\Api;

use App\Controllers\WithGameCodeParam;
use App\Core\Info;
use App\CQRS\Commands\AssignGameModeCommand;
use App\CQRS\Commands\RecalculateScoresCommand;
use App\CQRS\Commands\RecalculateSkillsCommand;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Game;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\CQRS\CommandBus;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Used for getting information about current games
 */
class GameHelpers extends ApiController
{
    use WithGameCodeParam;

    public function __construct(
      private readonly Config $config,
      private readonly CommandBus $commandBus,
    ) {}

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
            return $this->respond(
              new ErrorResponse('No game found', ErrorType::NOT_FOUND, values: ['games' => $allGames]),
              404
            );
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
     * @return ResponseInterface
     */
    public function getGateGameInfo() : ResponseInterface {
        /** @var Game|null $game */
        $game = Info::get('gate-game');

        if (!isset($game)) {
            return $this->respond(new ErrorResponse('No game found', ErrorType::NOT_FOUND), 404);
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
     * @param  string  $code
     * @return ResponseInterface
     * @throws Throwable
     */
    public function recalcSkill(string $code = '') : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        if ($this->commandBus->dispatch(new RecalculateSkillsCommand($game))) {
            return $this->respond(new SuccessResponse('Skills recalculated'));
        }

        return $this->respond(new ErrorResponse('Skills recalculation failed', ErrorType::INTERNAL), 500);
    }

    /**
     * @param  string  $code
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws GameModeNotFoundException
     * @throws JsonException
     * @throws Throwable
     */
    public function changeGameMode(string $code, Request $request) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        // Find game mode
        $gameModeId = (int) $request->getPost('mode', 0);
        if ($gameModeId < 1) {
            return $this->respond(new ErrorResponse('Invalid game mode ID', ErrorType::VALIDATION), 400);
        }
        $gameMode = GameModeFactory::getById($gameModeId, ['system' => $game::SYSTEM]);
        if (!isset($gameMode)) {
            return $this->respond(new ErrorResponse('Game mode not found', ErrorType::NOT_FOUND), 404);
        }

        $response = $this->commandBus->dispatch(new AssignGameModeCommand($game, $gameMode));

        if (!$response->success) {
            $errorType = $response->exception !== null ? ErrorType::INTERNAL : ErrorType::VALIDATION;
            $this->respond(
              new ErrorResponse($response->message, $errorType, exception: $response->exception),
              $errorType === ErrorType::INTERNAL ? 500 : 400
            );
        }

        return $this->respond(new SuccessResponse('Game mode changed'));
    }

    /**
     * @param  string  $code
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    public function recalcScores(string $code) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        if ($this->commandBus->dispatch(new RecalculateScoresCommand($game))) {
            return $this->respond(new SuccessResponse('Scores recalculated'));
        }

        return $this->respond(new ErrorResponse('Scores recalculation failed', ErrorType::INTERNAL), 500);
    }
}
