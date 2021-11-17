<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Models\Traits\WithGame;

abstract class Player extends AbstractModel
{
	use WithGame;

	public const PRIMARY_KEY = 'id_player';

	public const DEFINITION = [
		'game'     => [
			'validators' => ['required'],
			'class'      => Game::class,
		],
		'name'     => [
			'validators' => ['required'],
		],
		'score'    => [],
		'vest'     => [],
		'shots'    => [],
		'accuracy' => [],
		'hits'     => [],
		'deaths'   => [],
		'position' => [],
		'team'     => [
			'class' => Team::class,
		],
	];

	public int    $id_player;
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

	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('id_game = %i && name = %s', $this->game->id, $this->name)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
	}

}