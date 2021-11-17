<?php

namespace App\Models\Traits;

use App\Models\Game\Game;

trait WithGame
{

	protected ?Game $game;

	/**
	 * @return Game
	 */
	public function getGame() : Game {
		return $this->game;
	}

	/**
	 * @param Game $game
	 *
	 * @return WithGame
	 */
	public function setGame(Game $game) : static {
		$this->game = $game;
		return $this;
	}


}