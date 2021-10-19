<?php

namespace App\Models\Game;

class Timing
{

	/**
	 * @param int $before     Seconds before game
	 * @param int $gameLength Game length in minutes
	 * @param int $after      Seconds after game
	 */
	public function __construct(
		public int $before = 0,
		public int $gameLength = 0,
		public int $after = 0,
	) {
	}

}