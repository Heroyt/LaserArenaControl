<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\Evo5\Game as Evo5Game;
use App\Models\Game\Evo5\Team as Evo5Team;
use App\Models\Game\Game;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Game\Team;

class CSGO extends AbstractMode
{

	public string $name = 'CSGO';
	public int    $type = self::TYPE_TEAM;

	/**
	 * @param Evo5Game $game
	 *
	 * @return Evo5Team|null
	 */
	public function getWin(Game $game) : ?Team {
		$teams = $game->getTeams();
		// Two teams - get the last team alive or team with most hits
		if (count($teams) === 2) {
			/** @var Evo5Team $team1 */
			$team1 = $teams->first();
			$remaining1 = $this->getRemainingLives($team1);
			/** @var Evo5Team $team2 */
			$team2 = $teams->last();
			$remaining2 = $this->getRemainingLives($team2);
			if ($remaining1 === 0 && $remaining2 > 0) {
				return $team2;
			}
			if ($remaining1 > 0 && $remaining2 === 0) {
				return $team1;
			}
			if ($remaining1 > 0 && $remaining2 > 0) {
				$hits1 = $team1->getHits();
				$hits2 = $team2->getHits();
				if ($hits1 > $hits2) {
					return $team1;
				}
				if ($hits2 > $hits1) {
					return $team2;
				}
			}
			return null;
		}

		// More teams - Get alive team with the most hits
		$max = 0;
		$maxTeam = null;
		foreach ($teams as $team) {
			if ($this->getRemainingLives($team) === 0) {
				continue;
			}
			$hits = $team->getHits();
			if ($hits > $max) {
				$max = $hits;
				$maxTeam = $team;
			}
		}
		return $maxTeam;
	}

	/**
	 * @param Evo5Team $team
	 *
	 * @return int
	 */
	public function getRemainingLives(Evo5Team $team) : int {
		return $this->getTotalLives($team) - $team->getDeaths();
	}

	/**
	 * @param Evo5Team $team
	 *
	 * @return int
	 */
	public function getTotalLives(Evo5Team $team) : int {
		/** @var Evo5Game $game */
		$game = $team->getGame();
		return count($team->getPlayers()) * $game->lives;
	}

}