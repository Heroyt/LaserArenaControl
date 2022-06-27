<?php

if (!defined('ROOT')) {
	define('ROOT', dirname(__DIR__).'/');
}

require_once ROOT.'include/constants.php';

return [
	'parameters' => [
		'constants' => [
			'appDir'  => ROOT,
			'tempDir' => TMP_DIR,
		]
	]
];