<?php

namespace App\Install;

interface InstallInterface
{

	/**
	 * Install whatever the class needs
	 *
	 * @return bool Success
	 */
	public static function install(bool $fresh = false) : bool;

}