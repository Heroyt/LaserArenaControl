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
	/** @var Player */
	protected string $playerClass;
	/** @var PlayerCollection|Player[] */
	protected PlayerCollection $players;
	/** @var PlayerCollection|Player[] */
	protected PlayerCollection $playersSorted;

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
	 * @return PlayerCollection|Player[]
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->loadPlayers();
		}
		return $this->players;
	}

	/**
	 * @return PlayerCollection|Player[]
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