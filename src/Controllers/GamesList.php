<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use DateTime;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Exceptions\DirectoryCreationException;

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

	/**
	 *
	 * @param Game $game
	 *
	 * @return bool
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function checkGameTeamScores(Game $game) : bool {
		if ($game->gameType !== GameModeType::TEAM) {
			return true;
		}

		/** @var Player $player */
		foreach ($game->getPlayers() as $player) {
			if ($player->score > 0) {
				return true;
			}
		}
		return false;
	}

}