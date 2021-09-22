<?php
/**
 * @file    load.php
 * @brief   Main bootstrap
 * @details File which is responsible for loading all necessary components of the app
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @date    2021-09-22
 * @version 1.0
 * @since   1.0
 */

use App\Core\Loader;
use App\Logging\Tracy\DbTracyPanel;
use Tracy\Debugger;

if (!defined('ROOT')) {
	define("ROOT", dirname(__DIR__).'/');
}

date_default_timezone_set('Europe/Prague');

session_start();

// Autoload libraries
require_once ROOT.'vendor/autoload.php';

// Load all globals and constants
require_once ROOT.'include/config.php';

Debugger::enable(PRODUCTION ? Debugger::PRODUCTION : Debugger::DEVELOPMENT, LOG_DIR);
Debugger::getBar()->addPanel(new DbTracyPanel());

Loader::init();

