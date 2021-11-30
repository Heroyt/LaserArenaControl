<?php

namespace App\Install;

class Install implements InstallInterface
{

	public static function install(bool $fresh = false) : bool {
		return DbInstall::install($fresh) && Seeder::install($fresh);
	}

}