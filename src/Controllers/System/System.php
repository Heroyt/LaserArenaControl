<?php

namespace App\Controllers\System;

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
    ) {}

    #[OA\Get(
      path       : '/system/restart',
      operationId: 'systemRestart',
      description: 'Restart docker container.',
      tags       : ['System'],
    )]
    #[OA\Response(
      response   : 200,
      description: 'Ok response',
      content    : new OA\JsonContent(
        type   : 'string',
        example: 'Restarting...',
      )
    )]
    public function restart() : ResponseInterface {
        $this->metrics->add('restart_called', 1, ['roadrunner']);
        // start.sh is set up in a way to observe the restart.txt file and if its present, stop the container.
        // The restarting happens automatically due to the docker-compose "restart: unless-stopped" setting.
        touch(TMP_DIR.'restart.txt');
        return $this->respond('Restarting...');
    }

    #[OA\Get(
      path       : '/system/ffmpeg/restart',
      operationId: 'systemFFMPEGRestart',
      description: 'Restart ffmpeg docker container.',
      tags       : ['System'],
    )]
    #[OA\Response(
      response   : 200,
      description: 'Ok response',
      content    : new OA\JsonContent(
        type   : 'string',
        example: 'Restarting...',
      )
    )]
    public function restartFfmpeg() : ResponseInterface {
        $this->metrics->add('restart_called', 1, ['ffmpeg']);
        // start.sh is set up in a way to observe the restart.txt file and if its present, stop the container.
        // The restarting happens automatically due to the docker-compose "restart: unless-stopped" setting.
        $configDir = TMP_DIR.'streams';
        if (!file_exists($configDir) || !is_dir($configDir)) {
            return $this->respond('error - streams directory does not exist');
        }
        $configDir .= '/config';
        if (!file_exists($configDir) && !is_dir($configDir) && !mkdir($configDir) && !is_dir($configDir)) {
            return $this->respond('error - Cannot create streams/config directory');
        }
        touch($configDir.'/restart.txt');
        return $this->respond('Restarting...');
    }
}
