<?php
/**
 * @file  preload.php
 * @brief This files specifies all other PHP files which should be preloaded in OPCache.
 */

const ROOT = __DIR__.'/';

if (file_exists(ROOT . 'vendor/preload.php')) {
	require_once ROOT . 'vendor/preload.php';
}