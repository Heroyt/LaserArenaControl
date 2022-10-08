<?php

namespace App\Controllers;

use App\Core\Info;
use App\Tools\LMXController;
use Exception;
use JsonException;
use Lsr\Core\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;

/**
 *
 */
class GameControl extends Controller
{

	/**
	 * @return never
	 * @throws JsonException
	 */
	#[Get('/control/status', 'getGameStatus')]
	public function status() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		try {
			$response = LMXController::getStatus($ip);
		} catch (Exception $e) {
			$this->respond(['error' => 'Error while getting the game status', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['status' => $response]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/load', 'loadGame')]
	public function load(Request $request) : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$modeName = $request->post['mode'] ?? '';
		if (empty($modeName)) {
			$this->respond(['error' => 'Missing required parameter - mode'], 400);
		}
		$response = LMXController::load($ip, $modeName);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/loadSafe', 'loadGameSafe')]
	public function loadSafe(Request $request) : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$modeName = $request->post['mode'] ?? '';
		if (empty($modeName)) {
			$this->respond(['error' => 'Missing required parameter - mode'], 400);
		}
		try {
			$response = LMXController::getStatus($ip);
		} catch (Exception $e) {
			$this->respond(['error' => 'Error while getting the game status', 'exception' => $e->getMessage()], 500);
		}
		if ($response === 'PLAYING' || $response === 'DOWNLOAD') {
			$this->respond(['status' => $response]);
		}
		$response = LMXController::load($ip, $modeName);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/startSafe', 'startGameSafe')]
	public function startSafe() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		try {
			$response = LMXController::getStatus($ip);
		} catch (Exception $e) {
			$this->respond(['error' => 'Error while getting the game status', 'exception' => $e->getMessage()], 500);
		}
		if ($response === 'PLAYING' || $response === 'DOWNLOAD') {
			$this->respond(['status' => $response]);
		}
		if ($response === 'STANDBY') {
			$modeName = $request->post['mode'] ?? '';
			if (empty($modeName)) {
				$this->respond(['error' => 'Missing required parameter - mode'], 400);
			}
			$response = LMXController::loadStart($ip, $modeName);
		}
		else {
			$response = LMXController::start($ip);
		}
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/start', 'startGame')]
	public function start() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$response = LMXController::start($ip);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/stop', 'stopGame')]
	public function stop() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$response = LMXController::end($ip);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/retry', 'retryDownload')]
	public function retryDownload() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$response = LMXController::retryDownload($ip);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/control/cancel', 'cancelDownload')]
	public function cancelDownload() : never {
		/** @var string|null $ip */
		$ip = Info::get('lmx_ip');
		if (empty($ip)) {
			$this->respond(['error' => 'LaserMaxx IP is not defined'], 500);
		}
		$response = LMXController::cancelDownload($ip);
		if ($response !== 'ok') {
			$this->respond(['error' => 'API call failed', 'message' => $response], 500);
		}
		$this->respond(['status' => 'ok']);
	}

}