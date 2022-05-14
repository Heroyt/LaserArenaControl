<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
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