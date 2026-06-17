<?php

declare(strict_types=1);

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown if an invalid model collection query parameter is used.
 */
class InvalidQueryParameterException extends InvalidArgumentException
{
}
