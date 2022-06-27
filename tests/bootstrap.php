<?php

define('ROOT', dirname(__DIR__).'/');

$noDb = ['--testsuite=unit', '--testsuite="unit"'];

if (isset($GLOBALS['argv'][1]) && in_array($GLOBALS['argv'][1], $noDb, true)) {
	$_ENV['noDb'] = true;
}

require_once ROOT.'include/load.php';
