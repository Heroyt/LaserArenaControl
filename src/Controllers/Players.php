<?php

namespace App\Controllers;

use App\Models\Auth\Player;
use App\Services\PlayerProvider;
use InvalidArgumentException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

class Players extends Controller
{

	public function __construct(
		Latte                           $latte,
		private readonly PlayerProvider $playerProvider
	) {
		parent::__construct($latte);
	}

	public function getPlayer(string $code) : never {
		try {
			$player = Player::getByCode($code);
		} catch (InvalidArgumentException $e) {
			$this->respond(['error' => $e->getMessage(), 'code' => $code], 400);
		}
		if (!isset($player)) {
			$player = $this->playerProvider->findPublicPlayerByCode($code);
		}
		if (!isset($player)) {
			$this->respond(['error' => 'Player not found'], 404);
		}
		$this->respond($player);
	}

	public function syncPlayer(string $code) : never {
		$player = $this->playerProvider->findPublicPlayerByCode($code);
		if (!isset($player)) {
			$this->respond(['error' => 'Player not found'], 404);
		}
		if (!$player->save()) {
			$this->respond(['error' => 'Save failed'], 500);
		}
		$this->respond($player);
	}

	public function find(Request $request) : never {
		$this->respond(
			array_values(
				$this->playerProvider->findPlayersLocal(
					(string) $request->getGet('search', ''),
					empty($request->getGet('nomail', ''))
				)
			)
		);
	}

	public function findPublic(Request $request) : never {
		$this->respond(
			$this->playerProvider->findPlayersPublic(
				(string) $request->getGet('search', '')
			)
		);
	}

}