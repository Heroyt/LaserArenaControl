<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;
use App\Models\Traits\WithGame;
use App\Models\Traits\WithPlayers;
use Dibi\Row;

abstract class Team extends AbstractModel implements InsertExtendInterface
{
	use WithPlayers;
	use WithGame;

	public const PRIMARY_KEY = 'id_team';
	public const DEFINITION  = [
		'game'     => ['class' => Game::class, 'validators' => ['required']],
		'color'    => ['validators' => ['required']],
		'score'    => [],
		'position' => [],
		'name'     => ['validators' => ['required']],
	];

	public int    $id_team;
	public int    $color;
	public int    $score;
	public int    $position;
	public string $name;

	/**
	 * Parse data from DB into the object
	 *
	 * @param Row $row Row from DB
	 *
	 * @return InsertExtendInterface
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public static function parseRow(Row $row) : InsertExtendInterface {
		if (isset($row->id_team, static::$instances[static::TABLE][$row->id_team])) {
			return static::$instances[static::TABLE][$row->id_team];
		}
		return new static($row->id_team ?? 0);
	}

	/**
	 * Add data from the object into the data array for DB INSERT/UPDATE
	 *
	 * @param array $data
	 */
	public function addQueryData(array &$data) : void {
		$data[$this::PRIMARY_KEY] = $this->id;
	}

	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('id_game = %i && name = %s', $this->game->id, $this->name)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
	}

	/**
	 * @return int
	 */
	public function getShots() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->shots;
		}
		return $sum;
	}

	/**
	 * @return int
	 */
	public function getHits() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->hits;
		}
		return $sum;
	}

	public function getAccuracy() : float {
		return round(100 * $this->getHits() / $this->getShots(), 2);
	}

	/**
	 * @param Team $team
	 *
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getHitsTeam(Team $team) : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			foreach ($player->getHitsPlayers() as $hits) {
				if ($hits->playerTarget->getTeam()->color === $team->color) {
					$sum += $hits->count;
				}
			}
		}
		return $sum;
	}

	public function getTeamBgClass(bool $includeSystem = false) : string {
		return 'team-'.($includeSystem ? $this->getGame()::SYSTEM.'-' : '').$this->color;
	}

}