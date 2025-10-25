<?php

namespace App\Http\Controllers\Api;

use App\Models\System;
use App\Models\SystemType;
use App\Tools\GameLoading\GameLoader;
use InvalidArgumentException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;

class GameLoading extends ApiController
{
    public function __construct(
      private readonly GameLoader $loader,
      private readonly Metrics    $metrics,
    ) {}

    /**
     * @param  non-empty-string|int|System  $system
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws ModelNotFoundException
     */
    public function loadGame(string | int | System $system, Request $request) : ResponseInterface {
        $start = microtime(true);
        if (is_numeric($system)) {
            $system = System::get((int) $system);
        }
        elseif (is_string($system)) {
            $type = SystemType::tryFrom($system);
            if ($type === null) {
                return $this->respond(new ErrorResponse('Invalid system type'), 400);
            }
            $systems = System::getForType($type);
            if (empty($systems)) {
                return $this->respond(new ErrorResponse('No systems found'), 404);
            }
            $system = first($systems);
            assert($system !== null);
        }
        try {
            // @phpstan-ignore-next-line
            $meta = $this->loader->loadGame($system, $request->getParsedBody());
        } catch (InvalidArgumentException $e) {
            $this->metrics->set('load_time', (microtime(true) - $start) * 1000, [$system->type->value]);
            return $this->respond(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
        }
        $this->metrics->set('load_time', (microtime(true) - $start) * 1000, [$system->type->value]);
        return $this->respond(
          new SuccessResponse(
            values: [
                      'mode'      => $meta['mode'],
                      'music'     => $meta['music'],
                      'group'     => $meta['group'] ?? null,
                      'groupName' => $meta['groupName'] ?? null,
                      'system'    => $system,
                    ],
          )
        );
    }
}
