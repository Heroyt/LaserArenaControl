<?php

namespace App\Install;

use App\Core\DB;
use Dibi\Exception;

class DbInstall implements InstallInterface
{

	public const TABLES = [];

	public static function install() : bool {
		try {
			foreach (self::TABLES as $tableName => $definition) {
				DB::getConnection()->query("CREATE TABLE IF NOT EXISTS %n $definition", $tableName);
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

}