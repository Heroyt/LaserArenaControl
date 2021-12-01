<?php

namespace App\Models\Game;

use App\Core\Interfaces\InsertExtendInterface;
use App\Tools\Strings;
use Dibi\Row;

class ModeSettings implements InsertExtendInterface
{

	public function __construct(
		public bool $public = true,
		public bool $mines = true,
		public bool $partWin = true,
		public bool $partTeams = true,
		public bool $partPlayers = true,
		public bool $partHits = true,
		public bool $partBest = true,
		public bool $partBestDay = true,
		public bool $playerScore = true,
		public bool $playerShots = true,
		public bool $playerMiss = true,
		public bool $playerAccuracy = true,
		public bool $playerMines = true,
		public bool $playerPlayers = true,
		public bool $playerPlayersTeams = true,
		public bool $playerKd = true,
		public bool $playerFavourites = true,
		public bool $playerLives = true,
		public bool $teamScore = true,
		public bool $teamAccuracy = true,
		public bool $teamShots = true,
		public bool $teamHits = true,
		public bool $teamZakladny = true,
		public bool $bestScore = true,
		public bool $bestHits = true,
		public bool $bestDeaths = true,
		public bool $bestAccuracy = true,
		public bool $bestHitsOwn = true,
		public bool $bestDeathsOwn = true,
		public bool $bestShots = true,
		public bool $bestMiss = true,
		public bool $bestMines = true,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?InsertExtendInterface {
		$class = new self;
		foreach (get_object_vars($class) as $name => $val) {
			$column = Strings::toSnakeCase($name);
			if (isset($row->$column)) {
				$class->$name = $row->$column === 1;
			}
		}
		return $class;
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		foreach (get_object_vars($this) as $name => $val) {
			$column = Strings::toSnakeCase($name);
			$data[$column] = $this->$name ? 1 : 0;
		}
	}
}