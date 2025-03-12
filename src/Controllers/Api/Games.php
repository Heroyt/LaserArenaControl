<?php

namespace App\Controllers\Api;

use App\Controllers\WithGameCodeParam;
use App\CQRS\Commands\SetGameGroupCommand;
use App\CQRS\Queries\Games\GameListQuery;
use App\CQRS\Queries\Games\GameRowListQuery;
use App\DataObjects\Request\Api\Games\ListRequest;
use App\GameModels\Game\Team;
use App\Models\GameGroup;
use App\Services\Evo5\GameSimulator;
use App\Services\GameHighlight\GameHighlightService;
use App\Services\SyncService;
use Dibi\Exception;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Serializer\Mapper;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{
    use WithGameCodeParam;

    public function __construct(
      private readonly GameSimulator        $gameSimulator,
      private readonly GameHighlightService $highlightService,
      private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    public function cheat(string $code, Request $request) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        $player = $request->getPost('player', 0);
        if (empty($player)) {
            return $this->respond(['error' => 'Invalid player'], 400);
        }

        $playerObj = $game->players->get($player);
        if (!isset($playerObj)) {
            return $this->respond(['error' => 'Player not found'], 404);
        }

        $enemies = [];
        if ($game->mode?->isTeam()) {
            /** @var Team $team */
            foreach ($game->teams as $team) {
                if ($team->color === $playerObj->team->color) {
                    continue;
                }
                foreach ($team->players as $player2) {
                    $enemies[] = $player2;
                }
            }
        }

        $addHits = $request->getGet('addHits');
        if (isset($addHits)) {
            $hits = (int) $addHits;
            $playerObj->hits += $hits;
            for ($i = 0; $i < $hits; $i++) {
                $enemy = $enemies[array_rand($enemies)];
            }
        }

        return $this->respond($game);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    public function syncGames(Request $request) : ResponseInterface {
        $limit = (int) ($request->params['limit'] ?? 5);
        $timeout = $request->getGet('timeout');
        $timeout = isset($timeout) ? (float) $timeout : null;
        SyncService::syncGames($limit, $timeout);
        return $this->respond(['success' => true]);
    }

    /**
     *
     * @return ResponseInterface
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function syncGame(string $code) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        if (!$game->sync()) {
            return $this->respond(['error' => 'Synchronization failed'], 500);
        }

        return $this->respond(['status' => 'ok']);
    }

    /**
     * Get list of all games
     *
     * @throws Throwable
     * @throws Exception
     */
    public function listGames(Mapper $mapper, Request $request) : ResponseInterface {
        // Map and validate request
        $requestMapper = new RequestValidationMapper($mapper);
        $requestMapper->setRequest($request);
        $filters = $requestMapper->mapQueryToObject(ListRequest::class);

        $queryClass = $filters->expand ? GameListQuery::class : GameRowListQuery::class;
        $query = new $queryClass($filters->excludeFinished, $filters->date);

        if (!empty($filters->limit)) {
            $query->limit($filters->limit);
        }
        if (!empty($filters->offset)) {
            $query->offset($filters->offset);
        }
        if (!empty($filters->system)) {
            $query->system($filters->system);
        }
        if (!empty($filters->orderBy)) {
            $query->orderBy($filters->orderBy, $filters->desc);
        }
        return $this->respond($query->get());
    }

    /**
     * Get one game's data by its code
     *
     * @param  string  $code
     * @return ResponseInterface
     */
    public function getGame(string $code) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }
        return $this->respond($game);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    public function setGroup(string $code, Request $request) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        $group = null;
        $groupId = $request->getPost('groupId', 0);
        if ($groupId > 0) {
            try {
                $group = GameGroup::get($groupId);
            } catch (ModelNotFoundException | ValidationException | DirectoryCreationException $e) {
                return $this->respond(
                  new ErrorResponse('Group not found', ErrorType::NOT_FOUND, exception: $e),
                  404
                );
            }
        }

        if (($values = $this->commandBus->dispatch(new SetGameGroupCommand($game, $group))) !== false) {
            return $this->respond(new SuccessResponse('Group set', values: $values));
        }
        return $this->respond(new ErrorResponse('Group set failed', ErrorType::INTERNAL), 500);
    }

    public function simulate() : ResponseInterface {
        $this->gameSimulator->simulate();
        return $this->respond(new SuccessResponse('Last loaded game was simulated'));
    }

    public function getHighlights(string $code, Request $request) : ResponseInterface {
        $game = $this->getGameFromCode($code);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type->httpCode());
        }

        return $this->respond(
          $this->highlightService->getHighlightsForGame(
            $game,
            !$request->getGet('no-cache')
          )
        );
    }
}
