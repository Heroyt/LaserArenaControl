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


/** Root directory */

use App\Controllers\E404;
use App\Core\App;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Helpers\Tools\Timer;

const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;

// For CLI use - init some important functions
if (PHP_SAPI === 'cli') {
	// Async signals is necessary for interrupt handling
	pcntl_async_signals(true);
	/** @var string $_ command used to run the script */
	$_ = $_SERVER['_'] ?? '/usr/local/bin/php';
	if ($_ === '/bin/sh') {
		$_ = '/usr/local/bin/php';
	}
}

require_once ROOT."include/load.php";

$app = App::getInstance();

Timer::start('app');
try {
    $response = $app->run();
} catch (RouteNotFoundException $e) {
	bdump($e);
	// Handle 404 Error
	$controller = App::getContainer()->getByType(E404::class);
    $controller->init($app->getRequest());
    $response = $controller->show($app->getRequest());
}
Timer::stop('app');

updateTranslations();

bdump($response);

App::sendResponse($response);