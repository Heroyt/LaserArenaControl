<?php

declare(strict_types=1);

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when trying to add an incorrect object to a collection
 */
class InvalidCollectionClassException extends InvalidArgumentException
{
}
