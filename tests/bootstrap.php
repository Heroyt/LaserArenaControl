<?php

use App\Install\DbInstall;

define('ROOT', dirname(__DIR__).'/');
const INDEX = true;

if (!file_exists(ROOT.'private/config.ini')) {
	copy(ROOT.'tests/private/config.ini', ROOT.'private/config.ini');
}

/*$noDb = ['--testsuite=unit', '--testsuite="unit"'];

if (isset($GLOBALS['argv'][1]) && in_array($GLOBALS['argv'][1], $noDb, true)) {
	$_ENV['noDb'] = true;
}*/

require_once ROOT.'include/load.php';

DbInstall::install();
