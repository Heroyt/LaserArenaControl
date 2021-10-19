<?php

namespace App\Models\Traits;

use App\Models\Game\Player;
use App\Models\Game\PlayerCollection;

trait WithPlayers
{

	/** @var int */
	public int $playerCount;

	/** @var PlayerCollection */
	protected PlayerCollection $players;

	public function addPlayer(Player ...$players) : static {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		$this->players->add(...$players);
		return $this;
	}

	/**
	 * @return PlayerCollection
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		return $this->players;
	}
}