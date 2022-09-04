<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Vest;
use Lsr\Core\Controller;

class NewGame extends Controller
{

	protected string $title       = 'New game';
	protected string $description = '';

	public function show() : void {
		$this->params['system'] = $_GET['system'] ?? first(GameFactory::getSupportedSystems());
		$this->params['vests'] = Vest::getForSystem($this->params['system']);
		$this->params['colors'] = GameFactory::getAllTeamsColors()[$this->params['system']];
		$this->params['gameModes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
		$this->view('pages/new-game/index');
	}

}