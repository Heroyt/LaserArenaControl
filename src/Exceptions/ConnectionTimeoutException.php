<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ConnectionTimeoutException extends Exception
{
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     *
     * @link  http://php.net/manual/en/exception.construct.php
     *
     * @param  string  $message  [optional] The Exception message to throw.
     * @param  int  $code  [optional] The Exception code.
     * @param  int  $timeout
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     *
     * @since 5.1.0
     */
    public function __construct($message = "", $code = 0, public int $timeout = 60, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
