<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\Evo5\Player;
use App\Models\Game\Game;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Game\Team;

class Zakladny extends AbstractMode
{

	public string $name = 'Základny';

	/**
	 * @param Game $game
	 *
	 * @return \App\Models\Game\Evo5\Team|null
	 */
	public function getWin(Game $game) : ?Team {
		/** @var \App\Models\Game\Evo5\Team $team1 */
		$team1 = $game->getTeams()->first();
		$zakladny1 = $this->getBasesDestroyed($team1);
		/** @var \App\Models\Game\Evo5\Team $team2 */
		$team2 = $game->getTeams()->last();
		$zakladny2 = $this->getBasesDestroyed($team2);
		if ($zakladny1 > $zakladny2) {
			return $team2;
		}
		if ($zakladny1 < $zakladny2) {
			return $team1;
		}
		return null;
	}

	/**
	 * Get number of bases destroyed
	 *
	 * @param \App\Models\Game\Evo5\Team $team
	 *
	 * @return int
	 */
	public function getBasesDestroyed(\App\Models\Game\Evo5\Team $team) : int {
		return max(
			array_map(static function(Player $player) {
				return $player->bonus->shield;
			}, $team->getPlayers()->getAll())
		);
	}

}