<?php

namespace App\Install;

class Install implements InstallInterface
{

	public static function install() : bool {
		return DbInstall::install() && Seeder::install();
	}

}