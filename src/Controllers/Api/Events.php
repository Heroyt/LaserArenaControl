<?php

namespace App\Controllers\Api;

use App\Services\EventService;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class Events extends ApiController
{
    public function __construct(
      private readonly EventService $eventService
    ) {
        parent::__construct();
    }

    public function triggerEvent(Request $request) : ResponseInterface {
        $type = $request->getPost('type', '');
        if (empty($type)) {
            return $this->respond(['error' => 'Type must be a non-empty string'], 400);
        }
        $message = $request->getPost('message');
        if (!$this->eventService->trigger($type, $message ?? time())) {
            return $this->respond(['error' => 'Failed setting an event'], 500);
        }
        return $this->respond(['status' => 'ok']);
    }
}
