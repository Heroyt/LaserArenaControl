<?php

namespace App\Controllers\Api;

use App\Services\EventService;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

class Events extends ApiController
{

	public function __construct(
		Latte                         $latte,
		private readonly EventService $eventService
	) {
		parent::__construct($latte);
	}

	public function triggerEvent(Request $request): never {
		$type = $request->getPost('type', '');
		if (empty($type)) {
			$this->respond(['error' => 'Type must be a non-empty string'], 400);
		}
		$message = $request->getPost('message');
		if (!$this->eventService->trigger($type, $message ?? time())) {
			$this->respond(['error' => 'Failed setting an event'], 500);
		}
		$this->respond(['status' => 'ok']);
	}

}