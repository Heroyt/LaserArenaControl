<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Gate;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Gate\Gate;
use App\Gate\Logic\CustomEventDto;
use App\Gate\Models\GateType;
use App\Services\EventService;
use DateTime;
use DateTimeImmutable;
use Dibi\Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Gate is a page that displays actual results and information preferably on other visible display.
 */
class GateController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly Gate         $gate
    ) {
        parent::__construct();
    }

    /**
     * @param  string  $gate
     * @return ResponseInterface
     */
    public function show(Request $request, string $gate = 'default'): ResponseInterface {
        $system = $request->getGet('system', 'all');

        $gateType = GateType::getBySlug(empty($gate) ? 'default' : $gate);
        if (!isset($gateType)) {
            return $this->respond(
                new ErrorResponse('Gate type not found.', ErrorType::NOT_FOUND, values: ['slug' => $gate]),
                404
            );
        }

        try {
            $screen = $this->gate
              ->getCurrentScreen($gateType, $system)
              ->setParams($this->params);
            return $screen->run()
                          ->withHeader('Cache-Control', 'no-store')
                          ->withAddedHeader('X-Screen', $screen::getDiKey())
                          ->withAddedHeader('X-Trigger', $screen->getTrigger()?->value ?? 'none');
        } catch (ValidationException | Throwable $e) {
            return $this->respond(new ErrorResponse('An error has occured', exception: $e), 500);
        }
    }

    #[OA\Post(
        path: '/gate/event',
        operationId: 'setGateEvent',
        description: 'Set a gate event.',
        requestBody: new OA\RequestBody(
            required: true,
            content : new OA\JsonContent(
                required  : ["event"],
                properties: [
                                  new OA\Property(
                                      property: "event",
                                      description: 'Event name',
                                      type: "string",
                                      example: 'reload'
                                  ),
                                  new OA\Property(
                                      property   : "time",
                                      description: 'How long should the event be valid in seconds.',
                                      type       : "int",
                                      example    : '60'
                                  ),
                                ],
                type      : 'object',
            ),
        ),
        tags: ['Gate'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Event set',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    public function setEvent(Request $request): ResponseInterface {
        $event = (string) $request->getPost('event', '');
        $time = (int) $request->getPost('time', 60);

        $dto = new CustomEventDto($event, time() + $time);
        Info::set('gate-event', $dto);
        $this->eventService->trigger(
            'gate-reload',
            ['type' => 'custom-event', 'event' => $event, 'time' => $dto->time]
        );
        return $this->respond(new SuccessResponse());
    }

    #[OA\Post(
        path: '/gate/set/{system}',
        operationId: 'setGateGame',
        description: 'Set a gate active game.',
        requestBody: new OA\RequestBody(
            required: true,
            content : new OA\JsonContent(
                required  : ["game"],
                properties: [
                                  new OA\Property(
                                      property: "game",
                                      description: 'Game ID',
                                      oneOf : [
                                      new OA\Schema(description: 'Last game', type: 'string', enum: ['last']),
                                      new OA\Schema(description: 'Game ID', type: 'int', example: 1)
                                              ],
                                  ),
                                ],
                type      : 'object',
            ),
        ),
        tags: ['Gate'],
    )]
    #[OA\Parameter(
        name   : "system",
        description: 'Game system.',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type       : "string",
            enum: ['evo5', 'evo6']
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Game set',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'),
    )]
    #[OA\Response(
        response: 400,
        description: 'Request error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Game not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    public function setGateGame(Request $request): ResponseInterface {
        $game = $this->getGame($request);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
        }
        try {
            $gateTime = time();
            $game->end = new DateTimeImmutable();
            Info::set('gate-game', $game);
            Info::set('gate-time', $gateTime);
            $this->clearEvent();
            $this->eventService->trigger(
                'gate-reload',
                ['type' => 'game-set', 'game' => $game->code, 'time' => $gateTime]
            );
        } catch (Exception $e) {
            return $this->respond(
                new ErrorResponse('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
                500
            );
        }

        return $this->respond(new SuccessResponse());
    }

    /**
     * @param  Request  $request
     *
     * @return Game|ErrorResponse
     * @throws Throwable
     */
    private function getGame(Request $request): Game | ErrorResponse {
        /** @var 'last'|numeric $gamePost */
        $gamePost = $request->getPost('game', '0');
        $system = (string) $request->getParam('system', 'all');
        if (empty($system)) {
            return new ErrorResponse('Missing / Incorrect system', type: ErrorType::VALIDATION);
        }

        if ($gamePost === 'last') {
            $game = GameFactory::getLastGame($system);
            if (isset($game)) {
                return $game;
            }
        }

        $gameId = (int) $gamePost;
        if (empty($gameId)) {
            return new ErrorResponse('Missing / Incorrect game', type: ErrorType::VALIDATION);
        }
        $game = GameFactory::getById($gameId, ['system' => $system]);
        return $game ?? new ErrorResponse('Cannot find game', type: ErrorType::NOT_FOUND);
    }

    private function clearEvent(): void {
        Info::set('gate-event', null);
    }

    #[OA\Post(
        path: '/gate/loaded/{system}',
        operationId: 'setGateGameLoaded',
        description: 'Set a gate loaded game.',
        requestBody: new OA\RequestBody(
            required: true,
            content : new OA\JsonContent(
                required  : ["game"],
                properties: [
                                  new OA\Property(
                                      property: "game",
                                      description: 'Game ID',
                                      oneOf : [
                                                new OA\Schema(description: 'Last game', type: 'string', enum: ['last']),
                                                new OA\Schema(description: 'Game ID', type: 'int', example: 1)
                                              ],
                                  ),
                                ],
                type      : 'object',
            ),
        ),
        tags: ['Gate'],
    )]
    #[OA\Parameter(
        name   : "system",
        description: 'Game system.',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type       : "string",
            enum: ['evo5', 'evo6']
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Game set',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'),
    )]
    #[OA\Response(
        response: 400,
        description: 'Request error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Game not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    public function setGateLoaded(string $system, Request $request): ResponseInterface {
        $game = $this->getGame($request);
        if ($game instanceof ErrorResponse) {
            return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
        }
        try {
            Info::set('gate-game', null);
            $game->fileTime = new DateTime(); // Set time to NOW
            $game->start = null;
            $game->end = null;
            Info::set($system . '-game-loaded', $game);
            $this->clearEvent();
            $this->eventService->trigger(
                'gate-reload',
                ['type' => 'game-set-loaded', 'game' => $game->code, 'time' => time()]
            );
        } catch (Exception $e) {
            return $this->respond(
                new ErrorResponse('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
                500
            );
        }
        return $this->respond(new SuccessResponse());
    }

    #[OA\Post(
        path: '/gate/idle/{system}',
        operationId: 'setGateIdle',
        description: 'Set a gate to the idle state.',
        tags: ['Gate'],
    )]
    #[OA\Parameter(
        name   : "system",
        description: 'Game system.',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type       : "string",
            enum: ['evo5', 'evo6']
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Gate state set',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'),
    )]
    #[OA\Response(
        response: 400,
        description: 'Request error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        )
    )]
    public function setGateIdle(string $system = ''): ResponseInterface {
        if (empty($system)) {
            return $this->respond(new ErrorResponse('Missing / Incorrect system', type: ErrorType::VALIDATION), 400);
        }
        try {
            Info::set('gate-game', null);
            Info::set($system . '-game-loaded', null);
            $this->clearEvent();
            $this->eventService->trigger('gate-reload', ['type' => 'set-idle', 'time' => time()]);
        } catch (Exception $e) {
            return $this->respond(
                new ErrorResponse('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
                500
            );
        }
        return $this->respond(new SuccessResponse());
    }
}
