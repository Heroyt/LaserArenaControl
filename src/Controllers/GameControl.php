<?php

namespace App\Controllers;

use App\Models\System;
use App\Tools\LMXController;
use Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 *
 */
class GameControl extends Controller
{
    public function __construct(
      private readonly Metrics $metrics,
    ) {}

    public function status(?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $this->metrics->add('control_status', 1);
        $start = microtime(true);
        try {
            $response = LMXController::getStatus($ip);
        } catch (Exception $e) {
            $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['status']);
            return $this->respond(
              [
                'status'    => 'error',
                'error'     => 'Error while getting the game status',
                'exception' => $e->getMessage(),
              ],
              500
            );
        }
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['status']);
        return $this->respond(new SuccessResponse(values: ['status' => $response]));
    }

    public function loadSafe(Request $request, ?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $this->metrics->add('control_load', 1);
        $start = microtime(true);
        $modeName = $request->getPost('mode', '');
        if (empty($modeName)) {
            return $this->respond(new ErrorResponse('Missing required parameter - mode'), 400);
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
            return $this->respond(new SuccessResponse(values: ['status' => $response]));
        }
        $response = LMXController::load($ip, $modeName);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['loadSafe']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function load(Request $request, ?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
        $this->metrics->add('control_load', 1);
        $modeName = $request->getPost('mode', '');
        if (empty($modeName)) {
            return $this->respond(new ErrorResponse('Missing required parameter - mode'), 400);
        }
        $response = LMXController::load($ip, $modeName);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['load']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function startSafe(Request $request, ?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
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
            return $this->respond(new SuccessResponse(values: ['status' => $response]));
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
            $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['loadStart']);
        }
        else {
            $response = LMXController::start($ip);
            $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['start']);
        }
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function start(?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
        $this->metrics->add('control_start', 1);
        $response = LMXController::start($ip);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['start']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function stop(?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
        $this->metrics->add('control_stop', 1);
        $response = LMXController::end($ip);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['end']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function retryDownload(?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
        $response = LMXController::retryDownload($ip);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['retryDownload']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function cancelDownload(?System $system = null) : ResponseInterface {
        $ip = $this->getSystemIp($system);
        if ($ip === null) {
            return $this->respond(new ErrorResponse('LaserMaxx IP is not defined'), 500);
        }
        $start = microtime(true);
        $response = LMXController::cancelDownload($ip);
        $this->metrics->set('control_time', (microtime(true) - $start) * 1000, ['cancelDownload']);
        if ($response !== 'ok') {
            return $this->respond(new ErrorResponse('API call failed', detail: $response), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    /**
     * @return non-empty-string|null
     */
    private function getSystemIp(?System $system = null) : ?string {
        if ($system === null) {
            $system = System::getDefault();
        }

        if ($system === null || empty($system->systemIp)) {
            return null;
        }
        return $system->systemIp;
    }
}
