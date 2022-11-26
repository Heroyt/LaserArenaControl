<?php

namespace App\Controllers\Api;

use App\Core\Info;
use App\Tools\GatesController;
use Exception;
use Lsr\Core\ApiController;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;

class Gates extends ApiController
{

	/** @var string[] */
	private array $ips;

	public function __construct(Latte $latte) {
		parent::__construct($latte);
		$this->ips = (array) Info::get('gates_ips', []);
	}

	#[Post('api/gates/start')]
	public function start() : void {
		foreach ($this->ips as $ip) {
			try {
				GatesController::start($ip);
			} catch (Exception $e) {
				$this->respond(['error' => $e->getMessage()], 500);
			}
		}
		$this->respond(['status' => 'ok']);
	}

	#[Post('api/gates/stop')]
	public function stop() : void {
		foreach ($this->ips as $ip) {
			try {
				GatesController::end($ip);
			} catch (Exception $e) {
				$this->respond(['error' => $e->getMessage()], 500);
			}
		}
		$this->respond(['status' => 'ok']);
	}

}