<?php

namespace App\Tracy;

use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

final class SchedulerTracyLogger
{
    public static function log(Throwable $throwable) : void {
        Debugger::log($throwable, ILogger::EXCEPTION);
    }
}
