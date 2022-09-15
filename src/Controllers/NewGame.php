<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Vest;
use App\Models\MusicMode;
use Lsr\Core\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Post;

class NewGame extends Controller
{

	protected string $title       = 'New game';
	protected string $description = '';

	public function show() : void {
		$this->params['loadGame'] = !empty($_GET['game']) ? GameFactory::getByCode($_GET['game']) : null;
		$this->params['system'] = $_GET['system'] ?? first(GameFactory::getSupportedSystems());
		$this->params['vests'] = Vest::getForSystem($this->params['system']);
		$this->params['colors'] = GameFactory::getAllTeamsColors()[$this->params['system']];
		$this->params['teamNames'] = GameFactory::getAllTeamsNames()[$this->params['system']];
		$this->params['gameModes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
		$this->params['musicModes'] = MusicMode::getAll();
		$this->view('pages/new-game/index');
	}

	/**
	 * Create a new game
	 *
	 * @param Request $request
	 *
	 * @return never
	 */
	#[Post('/')]
	public function process(Request $request) : never {
		bdump($request->post);
		$this->respond('ok');
	}

}