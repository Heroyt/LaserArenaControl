<?php

namespace App\Controllers\Api;

use App\GameModels\Factory\GameFactory;
use App\Tasks\GameHighlightsTask;
use App\Tasks\GamePrecacheTask;
use App\Tasks\Payloads\GameHighlightsPayload;
use App\Tasks\Payloads\GamePrecachePayload;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Roadrunner\Tasks\TaskProducer;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;

class Tasks extends ApiController
{
    public function __construct(
      private readonly TaskProducer $taskProducer
    ) {
        parent::__construct();
    }

    #[OA\Post(
      path       : '/api/tasks/precache',
      operationId: 'planPrecacheTask',
      description: 'Plan a job task to precache a game',
      requestBody: new OA\RequestBody(
        required: true,
        content : new OA\JsonContent(
                    required  : ["game"],
                    properties: [
                                  new OA\Property(
                                    property   : "game",
                                    description: 'Game code',
                                    type       : "string",
                                  ),
                                ],
                    type      : 'object',
                  ),
      ),
      tags       : ['Tasks'],
    )]
    #[OA\Response(
      response   : 200,
      description: 'Plan successful',
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 404,
      description: 'Game not found',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    public function planGamePrecache(Request $request) : ResponseInterface {
        /** @var string $code */
        $code = $request->getPost('game', '');
        if (empty($code)) {
            return $this->respond(
              new ErrorResponse(
                        'Missing or invalid required post parameter `game`',
                        ErrorType::VALIDATION,
                // @phpstan-ignore-next-line
                values: $request->getParsedBody()
              ),
              400
            );
        }
        $game = GameFactory::getByCode($code);
        if (!isset($game)) {
            return $this->respond(
              new ErrorResponse('Game not found', ErrorType::NOT_FOUND),
              404
            );
        }

        /** @var numeric-string|null $style */
        $style = $request->getPost('style');
        /** @var string|null $template */
        $template = $request->getPost('template');

        try {
            $this->taskProducer->push(
              GamePrecacheTask::class,
              new GamePrecachePayload(
                $code,
                isset($style) ? (int) $style : null,
                isset($template) ? (string) $template : null,
              )
            );
        } catch (JobsException $e) {
            return $this->respond(new ErrorResponse('Failed to plan a job', exception: $e), 500);
        }

        return $this->respond('');
    }

    #[OA\Post(
      path       : '/api/tasks/highlights',
      operationId: 'planHighlightsTask',
      description: 'Plan a job task to get highlights for a game',
      requestBody: new OA\RequestBody(
        required: true,
        content : new OA\JsonContent(
                    required  : ["game"],
                    properties: [
                                  new OA\Property(
                                    property   : "game",
                                    description: 'Game code',
                                    type       : "string",
                                  ),
                                ],
                    type      : 'object',
                  ),
      ),
      tags       : ['Tasks'],
    )]
    #[OA\Response(
      response   : 200,
      description: 'Plan successful',
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 404,
      description: 'Game not found',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    public function planGameHighlights(Request $request) : ResponseInterface {
        /** @var string $code */
        $code = $request->getPost('game', '');
        if (empty($code)) {
            return $this->respond(
              new ErrorResponse(
                        'Missing or invalid required post parameter `game`',
                        ErrorType::VALIDATION,
                values: ['parsed' => $request->getParsedBody(), 'body' => $request->getBody()->getContents()]
              ),
              400
            );
        }
        $game = GameFactory::getByCode($code);
        if (!isset($game)) {
            return $this->respond(
              new ErrorResponse('Game not found', ErrorType::NOT_FOUND),
              404
            );
        }

        try {
            $this->taskProducer->push(
              GameHighlightsTask::class,
              new GameHighlightsPayload($code)
            );
        } catch (JobsException $e) {
            return $this->respond(new ErrorResponse('Failed to plan a job', exception: $e), 500);
        }

        return $this->respond('');
    }
}
