<?php

namespace App\Models\Traits;

use App\Core\DB;
use App\Models\Game\Game;
use App\Models\Game\Player;
use App\Models\Game\PlayerCollection;
use App\Models\Game\Team;

trait WithPlayers
{

	/** @var Player */
	protected string $playerClass;

	/** @var int */
	public int $playerCount;

	/** @var PlayerCollection */
	protected PlayerCollection $players;

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

	public function loadPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		$className = $this->playerClass;
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
	 * @return PlayerCollection
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->loadPlayers();
		}
		return $this->players;
	}

	public function savePlayers() : bool {
		foreach ($this->players as $player) {
			if (!$player->save()) {
				return false;
			}
		}
		return true;
	}
}