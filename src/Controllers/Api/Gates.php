<?php

namespace App\Controllers\Api;

use App\Core\Info;
use App\Tools\GatesController;
use Exception;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class Gates extends ApiController
{

	/** @var string[] */
	private array $ips;

	public function __construct(Latte $latte) {
		parent::__construct($latte);
		$this->ips = (array) Info::get('gates_ips', []);
	}

	#[Post('api/gates/start')]
	public function start(): ResponseInterface {
		foreach ($this->ips as $ip) {
			try {
				GatesController::start($ip);
			} catch (Exception $e) {
				return $this->respond(['status' => 'error', 'error' => $e->getMessage()], 500);
			}
		}
		return $this->respond(['status' => 'ok']);
	}

	#[Post('api/gates/stop')]
	public function stop(): ResponseInterface {
		foreach ($this->ips as $ip) {
			try {
				GatesController::end($ip);
			} catch (Exception $e) {
				return $this->respond(['status' => 'error', 'error' => $e->getMessage()], 500);
			}
		}
		return $this->respond(['status' => 'ok']);
	}

}