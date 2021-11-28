<?php

namespace App\Models\Game;

use App\Core\DB;
use App\Tools\Strings;
use Dibi\Fluent;

class Today
{

	public int $games;
	public int $teams;
	public int $players;

	private Fluent $gameQuery;

	public function __construct(Game $gameClass, Player $playerClass, Team $teamClass) {
		$this->games = DB::select($gameClass::TABLE, 'count(*)')->where('DATE(start) = %d', $gameClass->start)->fetchSingle();
		$this->players = DB::select($playerClass::TABLE, 'count(*)')->where('id_game IN %sql', $this->todayGames($gameClass))->fetchSingle();
		$this->teams = DB::select($teamClass::TABLE, 'count(*)')->where('id_game IN %sql', $this->todayGames($gameClass))->fetchSingle();
	}

	private function todayGames(Game $gameClass) : Fluent {
		$this->gameQuery = DB::select($gameClass::TABLE, 'id_game')->where('DATE(start) = %d', $gameClass->start);
		return $this->gameQuery;
	}

	/**
	 * @param Player $player
	 * @param string $property
	 *
	 * @return int
	 */
	public function getPlayerOrder(Player $player, string $property) : int {
		return DB::select($player::TABLE, 'count(*)')
						 ->where('[id_game] IN %sql AND %n <= %i', $this->gameQuery, Strings::toSnakeCase($property), $player->$property)
						 ->fetchSingle() - 1;
	}

}