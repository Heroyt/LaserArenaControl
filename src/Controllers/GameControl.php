<?php

namespace App\Controllers;

use App\Core\Info;
use App\Tools\LMXController;
use Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 *
 */
class GameControl extends Controller
{

    public function __construct(
      Latte                    $latte,
      private readonly Metrics $metrics,
    ) {
        parent::__construct($latte);
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     */
    #[Get('/control/status', 'getGameStatus')]
    public function status() : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_status', 1);
        try {
            $response = LMXController::getStatus($ip);
        } catch (Exception $e) {
            return $this->respond(
              [
                'status'    => 'error',
                'error'     => 'Error while getting the game status',
                'exception' => $e->getMessage(),
              ],
              500
            );
        }
        return $this->respond(['status' => $response]);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    #[Post('/control/loadSafe', 'loadGameSafe')]
    public function loadSafe(Request $request) : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_load', 1);
        $modeName = $request->getPost('mode', '');
        if (empty($modeName)) {
            return $this->respond(['status' => 'error', 'error' => 'Missing required parameter - mode'], 400);
        }
        try {
            $response = LMXController::getStatus($ip);
        } catch (Exception $e) {
            return $this->respond(
              [
                'status'    => 'error',
                'error'     => 'Error while getting the game status',
                'exception' => $e->getMessage(),
              ],
              500
            );
        }
        if ($response === 'PLAYING' || $response === 'DOWNLOAD') {
            return $this->respond(['status' => $response]);
        }
        $response = LMXController::load($ip, $modeName);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    #[Post('/control/load', 'loadGame')]
    public function load(Request $request) : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_load', 1);
        $modeName = $request->getPost('mode', '');
        if (empty($modeName)) {
            return $this->respond(['status' => 'error', 'error' => 'Missing required parameter - mode'], 400);
        }
        $response = LMXController::load($ip, $modeName);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    #[Post('/control/startSafe', 'startGameSafe')]
    public function startSafe(Request $request) : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_start', 1);
        try {
            $response = LMXController::getStatus($ip);
        } catch (Exception $e) {
            return $this->respond(
              [
                'status'    => 'error',
                'error'     => 'Error while getting the game status',
                'exception' => $e->getMessage(),
              ],
              500
            );
        }
        if ($response === 'PLAYING' || $response === 'DOWNLOAD') {
            return $this->respond(['status' => $response]);
        }
        if ($response === 'STANDBY') {
            $modeName = $request->getPost('mode', '');
            if (empty($modeName)) {
                return $this->respond(
                  [
                    'status' => 'error',
                    'error'  => 'Missing required parameter - mode',
                    'post'   => $request->getParsedBody(),
                  ],
                  400
                );
            }
            $response = LMXController::loadStart($ip, $modeName);
        }
        else {
            $response = LMXController::start($ip);
        }
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    #[Post('/control/start', 'startGame')]
    public function start() : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_start', 1);
        $response = LMXController::start($ip);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     *
     * @return ResponseInterface
     */
    #[Post('/control/stop', 'stopGame')]
    public function stop() : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $this->metrics->add('control_stop', 1);
        $response = LMXController::end($ip);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     *
     * @return ResponseInterface
     */
    #[Post('/control/retry', 'retryDownload')]
    public function retryDownload() : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $response = LMXController::retryDownload($ip);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

    /**
     *
     * @return ResponseInterface
     */
    #[Post('/control/cancel', 'cancelDownload')]
    public function cancelDownload() : ResponseInterface {
        /** @var string|null $ip */
        $ip = Info::get('lmx_ip');
        if (empty($ip)) {
            return $this->respond(['status' => 'error', 'error' => 'LaserMaxx IP is not defined'], 500);
        }
        $response = LMXController::cancelDownload($ip);
        if ($response !== 'ok') {
            return $this->respond(['status' => 'error', 'error' => 'API call failed', 'message' => $response], 500);
        }
        return $this->respond(['status' => 'ok']);
    }

}