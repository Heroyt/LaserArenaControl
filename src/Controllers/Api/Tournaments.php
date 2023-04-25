<?php

namespace App\Controllers\Api;

use App\Models\Tournament\Tournament;
use Lsr\Core\ApiController;
use Lsr\Core\Templating\Latte;
use App\Services\TournamentProvider;

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

	public function get(Tournament $tournament) : never {
		$this->respond($tournament);
	}

	public function sync() : never {
		if ($this->tournamentProvider->sync()) {
			$this->respond(['status' => 'ok']);
		}
		$this->respond(['status' => 'error'], 500);
	}

}