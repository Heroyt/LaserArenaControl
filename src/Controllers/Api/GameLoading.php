<?php

namespace App\Controllers\Api;

use App\Tools\GameLoading\GameLoader;
use InvalidArgumentException;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

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
	public function loadGame(string $system, Request $request): never {
		try {
			$meta = $this->loader->loadGame($system, $request->post);
		} catch (InvalidArgumentException $e) {
			$this->respond(['error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
		}
		$this->respond(['status' => 'ok', 'mode' => $meta['mode']]);
	}

}