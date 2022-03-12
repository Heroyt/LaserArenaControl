<?php

namespace App\Models\Factory;

use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Models\Game\Game;
use App\Tools\Strings;
use DateTime;
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

	/**
	 * Get games for the day
	 *
	 * @param DateTime $date
	 * @param bool     $excludeNotFinished
	 *
	 * @return Game[]
	 */
	public static function getByDate(DateTime $date, bool $excludeNotFinished = false) : array {
		$games = [];
		$query = self::queryGames($excludeNotFinished)->where('DATE([start]) = %d', $date)->orderBy('start')->desc();
		$rows = $query->fetchAll();
		foreach ($rows as $row) {
			$game = self::getById($row->id_game, $row->system);
			if (isset($game)) {
				$games[] = $game;
			}
		}
		return $games;
	}

	/**
	 * Get game counts for each dates
	 *
	 * @param string $format
	 * @param bool   $excludeNotFinished
	 *
	 * @return array<string,int>
	 */
	public static function getGamesCountPerDay(string $format = 'Y-m-d', bool $excludeNotFinished = false) : array {
		$rows = self::queryGameCountPerDay($excludeNotFinished)->fetchAll();
		$return = [];
		foreach ($rows as $row) {
			if (!isset($row->date)) {
				continue;
			}
			$return[$row->date->format($format)] = $row->count;
		}
		return $return;
	}

	/**
	 * @param bool $excludeNotFinished
	 *
	 * @return Fluent
	 */
	public static function queryGameCountPerDay(bool $excludeNotFinished = false) : Fluent {
		$query = DB::getConnection()->select('[date], count(*) as [count]');
		$queries = [];
		foreach (self::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[code], DATE([g$key].[start]) as [date]");
			if ($excludeNotFinished) {
				$q->where("[g$key].[end] IS NOT NULL");
			}
			$queries[] = (string) $q;
		}
		$query
			->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
			->groupBy('date');
		return $query;
	}

	/**
	 * Prepare a SQL query for all games (from all systems)
	 *
	 * @param bool $excludeNotFinished
	 *
	 * @return Fluent
	 */
	public static function queryGames(bool $excludeNotFinished = false) : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (self::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[id_game], %s as [system], [g$key].[code], [g$key].[start], [g$key].[end]", $system);
			if ($excludeNotFinished) {
				$q->where("[g$key].[end] IS NOT NULL");
			}
			$queries[] = (string) $q;
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
	 * Get team colors for all supported systems
	 *
	 * @return string[][]
	 */
	public static function getAllTeamsColors() : array {
		$colors = [];
		foreach (self::getSupportedSystems() as $system) {
			/** @var Game $className */
			$className = 'App\Models\Game\\'.ucfirst($system).'\Game';
			if (method_exists($className, 'getTeamColors')) {
				$colors[$system] = $className::getTeamColors();
			}
		}
		return $colors;
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