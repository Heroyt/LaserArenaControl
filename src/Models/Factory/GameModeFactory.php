<?php

namespace App\Models\Factory;

use App\Core\DB;
use App\Exceptions\GameModeNotFoundException;
use App\Models\Game\GameModes\AbstractMode;
use Nette\Utils\Strings;

class GameModeFactory
{

	/**
	 * @param string $modeName Raw game mode name
	 * @param int    $modeType Mode type: 0 = Solo, 1 = Team
	 * @param string $system   System name
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function find(string $modeName, int $modeType = AbstractMode::TYPE_TEAM, string $system = '') : AbstractMode {
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where('%s LIKE CONCAT(\'%\', sysName, \'%\')', $modeName)->fetch();
		if (isset($mode->system)) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$system = $mode->system;
		}
		$args = [];
		$classBase = 'App\\Models\\Game\\';
		$classSystem = '';
		if (!empty($system)) {
			$classSystem = Strings::firstUpper($system).'\\';
		}
		$classNamespace = 'GameModes\\';
		$className = '';
		if (isset($mode)) {
			$dbName = str_replace(' ', '', Strings::capitalize($mode->name));
			$class = $classBase.$classSystem.$classNamespace.$dbName;
			if (class_exists($class)) {
				$className = $dbName;
				$args[] = $mode->id_mode;
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