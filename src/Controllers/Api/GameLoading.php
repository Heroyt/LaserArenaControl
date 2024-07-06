<?php

namespace App\Controllers\Api;

use App\Tools\GameLoading\GameLoader;
use InvalidArgumentException;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;

class GameLoading extends ApiController
{
    public function __construct(
        private readonly GameLoader $loader,
        private readonly Metrics    $metrics,
    ) {
        parent::__construct();
    }

    /**
     * @param  non-empty-string  $system
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function loadGame(string $system, Request $request): ResponseInterface {
        $start = microtime(true);
        try {
            // @phpstan-ignore-next-line
            $meta = $this->loader->loadGame($system, $request->getParsedBody());
        } catch (InvalidArgumentException $e) {
            $this->metrics->set('load_time', (microtime(true) - $start) * 1000, [$system]);
            return $this->respond(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
        }
        $this->metrics->set('load_time', (microtime(true) - $start) * 1000, [$system]);
        return $this->respond(['status' => 'ok', 'mode' => $meta['mode'], 'music' => $meta['music']]);
    }
}
