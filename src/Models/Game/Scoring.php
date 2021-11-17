<?php

namespace App\Models\Game;

use App\Core\Interfaces\InsertExtendInterface;
use Dibi\Row;

class Scoring implements InsertExtendInterface
{

	public function __construct(
		public int $hitOther = 0,
		public int $hitOwn = 0,
		public int $deathOther = 0,
		public int $deathOwn = 0,
		public int $hitPod = 0,
		public int $shot = 0,
		public int $machineGun = 0,
		public int $invisibility = 0,
		public int $agent = 0,
		public int $shield = 0,
	) {
	}

	public static function parseRow(Row $row) : InsertExtendInterface {
		return new Scoring(
			$row->scoring_hit_other ?? 0,
			$row->scoring_hit_own ?? 0,
			$row->scoring_death_other ?? 0,
			$row->scoring_death_own ?? 0,
			$row->scoring_hit_pod ?? 0,
			$row->scoring_shot ?? 0,
			$row->scoring_power_machine_gun ?? 0,
			$row->scoring_power_invisibility ?? 0,
			$row->scoring_power_agent ?? 0,
			$row->scoring_power_shield ?? 0,
		);
	}

	public function addQueryData(array &$data) : void {
		$data['scoring_hit_other'] = $this->hitOther;
		$data['scoring_hit_own'] = $this->hitOwn;
		$data['scoring_death_other'] = $this->deathOther;
		$data['scoring_death_own'] = $this->hitOwn;
		$data['scoring_hit_pod'] = $this->hitPod;
		$data['scoring_shot'] = $this->shot;
		$data['scoring_power_machine_gun'] = $this->machineGun;
		$data['scoring_power_invisibility'] = $this->invisibility;
		$data['scoring_power_agent'] = $this->agent;
		$data['scoring_power_shield'] = $this->shield;
	}
}