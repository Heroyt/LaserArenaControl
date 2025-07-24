<?php

namespace App\Controllers\Api;

use App\Services\EventService;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class Events extends ApiController
{
    public function __construct(
      private readonly EventService $eventService
    ) {}

    public function triggerEvent(Request $request) : ResponseInterface {
        $type = $request->getPost('type', '');
        if (empty($type) || !is_string($type)) {
            return $this->respond(new ErrorResponse('Type must be a non-empty string', ErrorType::VALIDATION), 400);
        }
        /** @var string|array<string,string>|null $message */
        $message = $request->getPost('message');
        if (!$this->eventService->trigger($type, $message ?? time())) {
            return $this->respond(new ErrorResponse('Failed setting an event'), 500);
        }
        return $this->respond(new SuccessResponse('Event successfully triggered.'));
    }
}
