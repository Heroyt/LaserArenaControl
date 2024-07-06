<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Exceptions;

use Exception;

/**
 * An exception thrown when a game has an unknown game mode assigned to it
 */
class GameModeNotFoundException extends Exception
{
}
