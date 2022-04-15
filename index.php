<?php
/**
 * @file      index.php
 * @brief     Main php file accessed by user
 * @details   All user connections are directed here
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Core\App;

/** Root directory */
const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;

// For CLI use - init some important functions
if (PHP_SAPI === 'cli') {
	// Async signals is necessary for interrupt handling
	pcntl_async_signals(true);
	/** @var string $_ command used to run the script */
	$_ = $_SERVER['_'];
}

require_once ROOT."include/load.php";

App::run();

updateTranslations();