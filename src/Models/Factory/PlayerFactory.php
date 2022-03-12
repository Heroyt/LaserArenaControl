<?php

namespace App\Models\Factory;

use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Models\Game\Player;
use App\Tools\Strings;
use Dibi\Fluent;
use InvalidArgumentException;

class PlayerFactory
{

	/**
	 * Get a game model
	 *
	 * @param int    $id
	 * @param string $system
	 *
	 * @return Player|null
	 */
	public static function getById(int $id, string $system) : ?Player {
		if (empty($system)) {
			throw new InvalidArgumentException('System name is required.');
		}
		/** @var Player $className */
		$className = '\\App\\Models\\Game\\'.Strings::toPascalCase($system).'\\Player';
		if (!class_exists($className)) {
			throw new InvalidArgumentException('Game model of does not exist: '.$className);
		}
		try {
			$game = new $className($id);
		} catch (ModelNotFoundException $e) {
			return null;
		}
		return $game;
	}

	/**
	 * Prepare a SQL query for all players (from all systems)
	 *
	 * @param int[][] $gameIds
	 *
	 * @return Fluent
	 */
	public static function queryPlayers(array $gameIds) : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			if (empty($gameIds[$system])) {
				continue;
			}
			$q = DB::select(["[{$system}_players]", "[g$key]"], "[g$key].[id_player], [g$key].[id_game], [g$key].[id_team], %s as [system], [g$key].[name], [g$key].[score], [g$key].[accuracy], [g$key].[hits], [g$key].[deaths], [g$key].[shots]", $system)
						 ->where("[g$key].[id_game] IN %in", $gameIds[$system]);
			$queries[] = (string) $q;
		}
		$query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
		return $query;
	}

}