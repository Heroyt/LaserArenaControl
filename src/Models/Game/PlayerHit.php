<?php

namespace App\Models\Game;

use App\Core\DB;
use Dibi\Exception;

class PlayerHit
{

	public const TABLE = '';

	public function __construct(
		public Player $playerShot,
		public Player $playerTarget,
		public int    $count = 0,) {
	}

	/**
	 * @return bool
	 */
	public function save() : bool {
		$test = DB::select($this::TABLE, '*')->where('[id_player] = %i AND [id_target] = %i', $this->playerShot->id, $this->playerTarget->id)->fetch();
		$data = [
			'id_player' => $this->playerShot->id,
			'id_target' => $this->playerTarget->id,
			'count'     => $this->count,
		];
		try {
			if (isset($test)) {
				DB::update($this::TABLE, $data, ['[id_player] = %i AND [id_target] = %i', $this->playerShot->id, $this->playerTarget->id]);
			}
			else {
				DB::insert($this::TABLE, $data);
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

}