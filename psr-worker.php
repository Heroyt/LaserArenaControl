<?php

use App\Core\App;
use Lsr\Roadrunner\Server;
use Tracy\Debugger;

const ROOT = __DIR__.'/';

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('display_startup_errors', '1');

require_once ROOT."include/load.php";

$app = App::getInstance();

// Tracy logging
Debugger::$logDirectory = LOG_DIR.'tracy';
if (
  !file_exists(Debugger::$logDirectory) &&
  !mkdir(Debugger::$logDirectory, 0777, true) &&
  !is_dir(Debugger::$logDirectory)
) {
    Debugger::$logDirectory = LOG_DIR;
}

// Run application
$server = $app::getService('roadrunner.server');
assert($server instanceof Server);
$server->run();