<?php

namespace App\Models;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\ModelQuery;

class Vest extends AbstractModel
{

	public const TABLE       = 'system_vests';
	public const PRIMARY_KEY = 'id_vest';
	public const DEFINITION  = [
		'vestNum' => ['validators' => ['required'],],
		'system'  => ['validators' => ['required', 'system'],],
		'gridCol' => [],
		'gridRow' => [],
	];

	public int    $vestNum;
	public string $system;
	public ?int   $gridCol = null;
	public ?int   $gridRow = null;

	/**
	 * @param string $system
	 *
	 * @return Vest[]
	 */
	public static function getForSystem(string $system) : array {
		return self::querySystem($system)->get();
	}

	/**
	 * @param string $system
	 *
	 * @return ModelQuery
	 */
	public static function querySystem(string $system) : ModelQuery {
		return self::query()->where('system = %s', $system);
	}

	/**
	 * @param string $system
	 *
	 * @return int
	 */
	public static function getVestCount(string $system) : int {
		return self::querySystem($system)->count();
	}

	public static function getGridCols(string $system) : int {
		return DB::select(self::TABLE, 'MAX(grid_col)')->where('system = %s', $system)->fetchSingle();
	}

	public static function getGridRows(string $system) : int {
		return DB::select(self::TABLE, 'MAX(grid_row)')->where('system = %s', $system)->fetchSingle();
	}

	/**
	 * @param string $system
	 *
	 * @return object{cols:int,rows:int}
	 */
	public static function getGridDimensions(string $system) : object {
		return DB::select(self::TABLE, 'MAX([grid_col]) as [cols], MAX([grid_row]) as [rows]')->where('[system] = %s', $system)->fetch();
	}

}