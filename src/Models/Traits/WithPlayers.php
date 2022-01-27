<?php

namespace App\Models\Traits;

use App\Core\DB;
use App\Exceptions\ValidationException;
use App\Models\Game\Game;
use App\Models\Game\Player;
use App\Models\Game\PlayerCollection;
use App\Models\Game\Team;

trait WithPlayers
{

	/** @var int */
	public int $playerCount;
	/** @var string */
	protected string $playerClass;
	/** @var PlayerCollection */
	protected PlayerCollection $players;
	/** @var PlayerCollection */
	protected PlayerCollection $playersSorted;

	/**
	 * @return int
	 */
	public function getMinScore() : int {
		/** @var Player|null $player */
		$player = $this->getPlayers()->query()->sortBy('score')->asc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	/**
	 * @return int
	 */
	public function getMaxScore() : int {
		/** @var Player|null $player */
		$player = $this->getPlayers()->query()->sortBy('score')->desc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	public function addPlayer(Player ...$players) : static {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		$this->players->add(...$players);
		foreach ($players as $player) {
			$player->setTeam($this);
		}
		return $this;
	}

	/**
	 * @return PlayerCollection|Player[]
	 */
	public function getPlayersSorted() : PlayerCollection {
		if (!isset($this->playersSorted)) {
			$this->playersSorted = $this
				->getPlayers()
				->query()
				->sortBy('score')
				->desc()
				->get();
		}
		return $this->playersSorted;
	}

	/**
	 * @return PlayerCollection
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->loadPlayers();
		}
		return $this->players;
	}

	/**
	 * @return PlayerCollection
	 */
	public function loadPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		$className = preg_replace(['/(.+)Game$/', '/(.+)Team$/'], '${1}Player', get_class($this));
		$primaryKey = $className::PRIMARY_KEY;
		$rows = DB::select($className::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $this->id)->fetchAll();
		foreach ($rows as $row) {
			/** @var Player $player */
			$player = new $className($row->$primaryKey, $row);
			if ($this instanceof Game) {
				$player->setGame($this);
			}
			else if ($this instanceof Team) {
				$player->setTeam($this);
			}
			$this->players->set($player, $player->vest);
		}
		return $this->players;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function savePlayers() : bool {
		/** @var Player $player */
		// Save players first
		foreach ($this->players as $player) {
			if (!$player->save()) {
				return false;
			}
		}
		// Save player hits
		foreach ($this->players as $player) {
			if (!$player->saveHits()) {
				return false;
			}
		}
		return true;
	}
}