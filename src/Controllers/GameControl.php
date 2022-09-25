<?php

namespace App\Controllers;

use App\Core\Info;
use App\Tools\LMXController;
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
		$response = LMXController::getStatus($ip);
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
			$this->respond(['error' => 'API call failed'], 500);
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
			$this->respond(['error' => 'API call failed'], 500);
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
			$this->respond(['error' => 'API call failed'], 500);
		}
		$this->respond(['status' => 'ok']);
	}

}