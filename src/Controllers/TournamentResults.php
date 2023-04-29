<?php

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use Lsr\Core\Constants;
use Lsr\Core\Controller;

class TournamentResults extends Controller
{

	use CommonGateMethods;

	public function gate(Tournament $tournament): void {
		$this->params['tournament'] = $tournament;
		$this->params['style'] = PrintStyle::getActiveStyle();

		// Allow for filtering games just from one system
		$system = $_GET['system'] ?? 'all';
		$systems = [$system];

		// Fallback to all available systems
		if ($system === 'all') {
			$systems = GameFactory::getSupportedSystems();
		}

		$now = time();

		/** @var Game|null $test */
		$test = Info::get('gate-game');
		/** @var int $gateTime */
		$gateTime = Info::get('gate-time', $now);
		if (isset($test) && ($now - $gateTime) <= Constants::TMP_GAME_RESULTS_TIME) {
			$this->params['reloadTimer'] = Constants::TMP_GAME_RESULTS_TIME - ($now - $gateTime) + 2;
			header('X-Reload-Time: ' . $this->params['reloadTimer']);
			$this->game = $test;
			if ($this->checkTournamentGame($tournament)) {
				$this->getResults();
				return;
			}
		}

		// Get the results of the last game played if it had finished in the last 2 minutes
		$lastGame = GameFactory::getLastGame($system);
		if (isset($lastGame) && ($now - $lastGame->end?->getTimestamp()) <= Constants::GAME_RESULTS_TIME) {
			$this->params['reloadTimer'] = Constants::GAME_RESULTS_TIME - ($now - $lastGame->end?->getTimestamp()) + 2;
			header('X-Reload-Time: ' . $this->params['reloadTimer']);
			$this->game = $lastGame;
			$this->getResults();
			return;
		}

		// Try to find the last loaded or started games in selected systems
		foreach ($systems as $system) {
			/** @var Game|null $started */
			$started = Info::get($system . '-game-started');
			if (isset($started) && ($now - $started->start?->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $started->fileTime) {
					continue;
				}
				$this->params['reloadTimer'] = Constants::GAME_STARTED_TIME - ($now - $started->start?->getTimestamp()) + 2;
				$started->end = null;
				$started->finished = false;
				$this->game = $started;
			}
		}

		if (isset($this->params['reloadTimer'])) {
			header('X-Reload-Time: ' . $this->params['reloadTimer']);
		}

		$this->getIdle($tournament);
	}

	private function checkTournamentGame(Tournament $tournament): bool {
		return isset($this->game) && $this->game->getTournamentGame() !== null && $this->game->getTournamentGame()->tournament->id === $tournament->id;
	}

	private function getIdle(Tournament $tournament): void {
		$this->params['game'] = $this->game;
		$this->params['games'] = $tournament->getGames();
		$this->params['teams'] = $tournament->getTeams();

		usort($this->params['teams'], static function (Team $a, Team $b) {
			$diff = $b->points - $a->points;
			if ($diff !== 0) {
				return $diff;
			}
			return $b->getScore() - $a->getScore();
		});

		$this->view('pages/tournaments/gate');
	}

}