<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
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
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('id_game = %i && name = %s', $this->game->id, $this->name)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
	}

	/**
	 * @return bool
	 */
	public function saveHits() : bool {
		foreach ($this->hitPlayers as $hits) {
			if (!$hits->save()) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return PlayerHit[]
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getHitsPlayers() : array {
		if (empty($this->hitPlayers)) {
			return $this->loadHits();
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player $player
	 *
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getHitsPlayer(Player $player) : int {
		return $this->getHitsPlayers()[$player->vest]?->count ?? 0;
	}

	/**
	 * @return PlayerHit[]
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function loadHits() : array {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
		foreach ($hits as $row) {
			/** @noinspection PhpParamsInspection */
			$this->addHits($this::get($row->id_target), $row->count);
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player $player
	 * @param int    $count
	 *
	 * @return $this
	 */
	public function addHits(Player $player, int $count) : Player {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$this->hitPlayers[$player->vest] = new $className($this, $player, $count);
		return $this;
	}

	public function getTodayPosition(string $property) : int {
		return 0; // TODO: Implement
	}

	public function getMiss() : int {
		return $this->shots - $this->hits;
	}

	/**
	 * Get a player that this player hit the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getFavouriteTarget() : ?Player {
		$max = 0;
		$maxPlayer = null;
		foreach ($this->getHitsPlayers() as $hits) {
			if ($hits->count > $max) {
				$maxPlayer = $hits->playerTarget;
				$max = $hits->count;
			}
		}
		return $maxPlayer;
	}

	/**
	 * Get a player that hit this player the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getFavouriteTargetOf() : ?Player {
		$max = 0;
		$maxPlayer = null;
		foreach ($this->getGame()->getPlayers() as $player) {
			if ($player->id === $this->id) {
				continue;
			}
			$hits = $player->getHitsPlayer($this);
			if ($hits > $max) {
				$max = $hits;
				$maxPlayer = $player;
			}
		}
		return $maxPlayer;
	}

}