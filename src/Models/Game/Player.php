<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Models\Traits\WithGame;

abstract class Player extends AbstractModel
{
	use WithGame;

	public int    $id;
	public string $name;
	public int    $score;
	public int    $vest;
	public int    $shots;
	public int    $accuracy;
	public int    $hits;
	public int    $deaths;
	public int    $position;

	/** @var PlayerHit[] */
	public array $hitPlayers = [];

	public int $teamNum;

	protected ?Team $team;

	/**
	 * @return Team|null
	 */
	public function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @param Team $team
	 *
	 * @return Player
	 */
	public function setTeam(Team $team) : Player {
		$this->team = $team;
		return $this;
	}

	/**
	 * @param Player $player
	 * @param int    $count
	 *
	 * @return $this
	 */
	public function addHits(Player $player, int $count) : Player {
		$this->hitPlayers[$player->vest] = new PlayerHit($this, $player, $count);
		return $this;
	}

}