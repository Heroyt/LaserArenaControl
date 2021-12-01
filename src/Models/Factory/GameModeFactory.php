<?php

namespace App\Models\Factory;

use App\Core\DB;
use App\Exceptions\GameModeNotFoundException;
use App\Models\Game\GameModes\AbstractMode;
use Dibi\Row;
use Nette\Utils\Strings;

class GameModeFactory
{

	/**
	 * @param int $id
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function getById(int $id) : AbstractMode {
		$mode = DB::select('game_modes', 'id_mode, name, system, type')->where('id_mode = %i', $id)->fetch();
		$system = $mode->system ?? '';
		$modeType = ($mode->type ?? 'TEAM') === 'TEAM' ? AbstractMode::TYPE_TEAM : AbstractMode::TYPE_SOLO;
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param string $modeName Raw game mode name
	 * @param int    $modeType Mode type: 0 = Solo, 1 = Team
	 * @param string $system   System name
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function find(string $modeName, int $modeType = AbstractMode::TYPE_TEAM, string $system = '') : AbstractMode {
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where('%s LIKE CONCAT(\'%\', [sysName], \'%\')', $modeName)->fetch();
		if (isset($mode->system)) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$system = $mode->system;
		}
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param string         $system
	 * @param array|Row|null $mode
	 * @param int            $modeType
	 *
	 * @return mixed
	 * @throws GameModeNotFoundException
	 */
	protected static function findModeObject(string $system, array|Row|null $mode, int $modeType) : mixed {
		$args = [];
		$classBase = 'App\\Models\\Game\\';
		$classSystem = '';
		if (!empty($system)) {
			$classSystem = Strings::firstUpper($system).'\\';
		}
		$classNamespace = 'GameModes\\';
		$className = '';
		if (isset($mode)) {
			$dbName = str_replace([' ', '.', '_', '-'], '', Strings::toAscii(Strings::capitalize($mode->name)));
			$class = $classBase.$classSystem.$classNamespace.$dbName;
			$args[] = $mode->id_mode;
			if (class_exists($class)) {
				$className = $dbName;
			}
			else if ($modeType === AbstractMode::TYPE_TEAM) {
				$classSystem = '';
				$className = 'CustomTeamMode';
			}
			else {
				$classSystem = '';
				$className = 'CustomSoloMode';
			}
		}

		if (empty($className)) {
			if ($modeType === AbstractMode::TYPE_TEAM) {
				$className = 'TeamDeathmach';
			}
			else {
				$className = 'Deathmach';
			}
		}
		$class = $classBase.$classSystem.$classNamespace.$className;
		if (!class_exists($class)) {
			$class = $classBase.$classNamespace.$className;
		}
		if (!class_exists($class)) {
			throw new GameModeNotFoundException('Cannot find game mode class: '.(isset($dbName) ? $classBase.$classSystem.$classNamespace.$dbName.'|' : '').$classBase.$classSystem.$classNamespace.$className.'|'.$classBase.$classNamespace.$className);
		}
		return new $class(...$args);
	}


}