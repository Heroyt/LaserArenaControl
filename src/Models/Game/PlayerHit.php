<?php

namespace App\Models\Game;

class PlayerHit
{

	public function __construct(
		public Player $playerShot,
		public Player $playerTarget,
		public int    $count = 0,) {
	}

}