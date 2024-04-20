<?php

namespace App\Controllers\Api;

use App\Tools\GameLoading\GameLoader;
use InvalidArgumentException;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class GameLoading extends ApiController
{

	public function __construct(Latte $latte, private readonly GameLoader $loader) {
		parent::__construct($latte);
	}

	/**
	 * @param string  $system
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function loadGame(string $system, Request $request): ResponseInterface {
		try {
			$meta = $this->loader->loadGame($system, $request->getParsedBody());
		} catch (InvalidArgumentException $e) {
			return $this->respond(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
		}
      return $this->respond(['status' => 'ok', 'mode' => $meta['mode'], 'music' => $meta['music']]);
	}

}