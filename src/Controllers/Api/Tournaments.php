<?php

namespace App\Controllers\Api;

use App\Models\Tournament\Tournament;
use App\Services\TournamentProvider;
use Lsr\Core\ApiController;
use Lsr\Core\Templating\Latte;

class Tournaments extends ApiController
{

	public function __construct(
		Latte $latte,
		private readonly TournamentProvider $tournamentProvider,
	) {
		parent::__construct($latte);
	}

	public function getAll() : never {
		$this->respond(Tournament::getAll());
	}

	public function get(Tournament $tournament): never {
		$this->respond($tournament);
	}

	public function sync(): never {
		if ($this->tournamentProvider->sync()) {
			$this->respond(['status' => 'ok']);
		}
		$this->respond(['status' => 'error'], 500);
	}

	public function syncGames(Tournament $tournament): never {
		if ($this->tournamentProvider->syncGames($tournament)) {
			$this->respond(['status' => 'ok']);
		}
		$this->respond(['status' => 'error'], 500);
	}

}