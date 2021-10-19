<?php

namespace App\Models\Game;

class Scoring
{

	public function __construct(
		public int $deathOther = 0,
		public int $hitOther = 0,
		public int $deathOwn = 0,
		public int $hitOwn = 0,
		public int $hitPod = 0,
		public int $shot = 0,
		public int $machineGun = 0,
		public int $invisibility = 0,
		public int $agent = 0,
		public int $shield = 0,
	) {
	}

}