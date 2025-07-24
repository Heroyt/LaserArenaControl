<?php

namespace App\Controllers\System;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Spiral\Goridge\RPC\AsyncRPCInterface;
use Spiral\RoadRunner\Metrics\MetricsInterface;

/**
 *
 */
class Roadrunner extends Controller
{
    public function __construct(
      private readonly AsyncRPCInterface $rpc,
      private readonly MetricsInterface  $metrics,
    ) {}

    #[OA\Get(
      path       : '/roadrunner/reset',
      operationId: 'roadrunnerReset',
      description: 'Reset roadrunner worker(s).',
      tags       : ['Roadrunner', 'System'],
    )]
    #[OA\Parameter(name: 'service', in: 'query', required: false, example: 'http')]
    #[OA\Response(
      response   : 200,
      description: 'Ok response',
      content    : new OA\JsonContent(
        type   : 'string',
        example: 'Resetting workers...',
      )
    )]
    public function reset(Request $request) : ResponseInterface {
        /** @var non-empty-string $service */
        $service = $request->getPost('service', 'all');
        $this->metrics->add('reset_called', 1, [$service]);
        if ($service === 'all') {
            $this->resetAll();
        }
        else {
            $this->rpc->callIgnoreResponse('resetter.Reset', $service);
        }
        return $this->respond('Restarting...');
    }

    private function resetAll() : void {
        /** @var string[] $list */
        $list = $this->rpc->call('resetter.List', true);
        foreach ($list as $service) {
            $this->rpc->callIgnoreResponse('resetter.Reset', $service);
        }
    }
}
