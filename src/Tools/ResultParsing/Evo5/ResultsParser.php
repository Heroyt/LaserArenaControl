<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\ResultParsing\Evo5;

use App\GameModels\Game\Evo5\Game;
use App\Tools\ResultParsing\WithExtensions;

/**
 * Result parser for the EVO5 system
 *
 * @use WithExtensions<Game>
 */
class ResultsParser extends \Lsr\Lg\Results\LaserMaxx\Evo5\ResultsParser
{
    use WithExtensions;
}
