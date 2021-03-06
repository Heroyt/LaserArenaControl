<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use DateTime;
use Lsr\Core\Controller;
use Lsr\Core\Requests\Request;

class GamesList extends Controller
{

	protected string $title       = 'Games list';
	protected string $description = '';

	public function show() : void {
		$this->params['date'] = new DateTime($_GET['date'] ?? 'now');
		$this->params['games'] = GameFactory::getByDate($this->params['date'], true);
		$this->params['gameCountsPerDay'] = GameFactory::getGamesCountPerDay('d.m.Y');
		$this->view('pages/games-list/index');
	}

	public function game(Request $request) : void {
		$this->view('pages/dashboard/index');
	}

}