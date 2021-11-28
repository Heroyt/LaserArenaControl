<?php

namespace App\Models\Factory;

use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Models\Game\Game;
use App\Tools\Strings;
use Dibi\Fluent;
use InvalidArgumentException;

class GameFactory
{

	/**
	 * Get game by its unique code
	 *
	 * @param string $code
	 *
	 * @return Game|null
	 */
	public static function getByCode(string $code) : ?Game {
		$game = self::queryGames()->where('[code] = %s', $code)->fetch();
		if (isset($game)) {
			return self::getById($game->id_game, $game->system);
		}
		return null;
	}

	protected static function queryGames() : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (self::getSupportedSystems() as $key => $system) {
			$queries[] = (string) DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[id_game], %s as [system], [g$key].[code], [g$key].[start]", $system);
		}
		$query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
		return $query;
	}

	/**
	 * Get a list of all supported systems
	 *
	 * @return string[]
	 */
	public static function getSupportedSystems() : array {
		return require ROOT.'config/supportedSystems.php';
	}

	/**
	 * Get a game model
	 *
	 * @param int    $id
	 * @param string $system
	 *
	 * @return Game|null
	 */
	public static function getById(int $id, string $system) : ?Game {
		if (empty($system)) {
			throw new InvalidArgumentException('System name is required.');
		}
		/** @var Game $className */
		$className = '\\App\\Models\\Game\\'.Strings::toPascalCase($system).'\\Game';
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

}