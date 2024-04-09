<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\GameModels\Factory\GameFactory;
use App\Services\TaskProducer;
use App\Tasks\GameHighlightsTask;
use App\Tasks\GamePrecacheTask;
use App\Tasks\Payloads\GameHighlightsPayload;
use App\Tasks\Payloads\GamePrecachePayload;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class Tasks extends ApiController
{

	public function __construct(
		Latte                         $latte,
		private readonly TaskProducer $taskProducer
	) {
		parent::__construct($latte);
	}

	public function planGamePrecache(Request $request) : ResponseInterface {
		/** @var string $code */
		$code = $request->getPost('game', '');
		if (empty($code)) {
			return $this->respond(
				new ErrorDto('Missing or invalid required post parameter `game`',
				             ErrorType::VALIDATION,
					values:    $request->getParsedBody()),
				400
			);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(
				new ErrorDto('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}

		/** @var numeric-string|null $style */
		$style = $request->getPost('style');
		/** @var string|null $template */
		$template = $request->getPost('template');

		$this->taskProducer->push(
			GamePrecacheTask::class,
			new GamePrecachePayload(
				$code,
				isset($style) ? (int) $style : null,
				isset($template) ? (string) $template : null,
			)
		);

		return $this->respond('');
	}

	public function planGameHighlights(Request $request) : ResponseInterface {
		/** @var string $code */
		$code = $request->getPost('game', '');
		if (empty($code)) {
			return $this->respond(
				new ErrorDto(
					        'Missing or invalid required post parameter `game`',
					        ErrorType::VALIDATION,
					values: ['parsed' => $request->getParsedBody(), 'body' => $request->getBody()->getContents()]
				),
				400
			);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(
				new ErrorDto('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}

		$this->taskProducer->push(
			GameHighlightsTask::class,
			new GameHighlightsPayload($code)
		);

		return $this->respond('');
	}

}