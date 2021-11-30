<?php

namespace App\Pages;

use App\Core\Page;
use App\Core\Request;
use App\Models\Factory\GameFactory;
use DateTime;

class GamesList extends Page
{

	protected string $title       = 'Games list';
	protected string $description = '';

	public function show() : void {
		$this->params['date'] = new DateTime($_GET['date'] ?? 'now');
		$this->params['games'] = GameFactory::getByDate($this->params['date']);
		$this->params['gameCountsPerDay'] = GameFactory::getGamesCountPerDay('d.m.Y');
		$this->view('pages/games-list/index');
	}

	public function game(Request $request) : void {
		$this->view('pages/dashboard/index');
	}

}