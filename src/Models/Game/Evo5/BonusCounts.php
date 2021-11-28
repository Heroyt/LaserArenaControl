<?php

namespace App\Models\Game\Evo5;

use App\Core\Interfaces\InsertExtendInterface;
use Dibi\Row;

class BonusCounts implements InsertExtendInterface
{

	public function __construct(
		public int $agent = 0,
		public int $invisibility = 0,
		public int $machineGun = 0,
		public int $shield = 0,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : InsertExtendInterface {
		return new self(
			$row->bonus_agent ?? 0,
			$row->bonus_invisibility ?? 0,
			$row->bonus_machine_gun ?? 0,
			$row->bonus_shield ?? 0,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data['bonus_agent'] = $this->agent;
		$data['bonus_invisibility'] = $this->invisibility;
		$data['bonus_machine_gun'] = $this->machineGun;
		$data['bonus_shield'] = $this->shield;
	}

	public function getSum() : int {
		return $this->agent + $this->invisibility + $this->machineGun + $this->shield;
	}
}