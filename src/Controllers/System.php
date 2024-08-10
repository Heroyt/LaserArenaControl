<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\MetricsInterface;

/**
 *
 */
class System extends Controller
{
    public function __construct(
        private readonly MetricsInterface $metrics,
    ) {
        parent::__construct();
    }

    #[OA\Get(
        path: '/system/restart',
        operationId: 'systemRestart',
        description: 'Restart docker container.',
        tags: ['System'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Ok response',
        content: new OA\JsonContent(
            type: 'string',
            example: 'Restarting...',
        )
    )]
    public function restart(): ResponseInterface {
        $this->metrics->add('restart_called', 1);
        // start.sh is set up in a way to observe the restart.txt file and if its present, stop the container.
        // The restarting happens automatically due to the docker-compose "restart: unless-stopped" setting.
        touch(TMP_DIR . 'restart.txt');
        return $this->respond('Restarting...');
    }
}
