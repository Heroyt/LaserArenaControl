<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\ResultParsing\Evo6;

use App\GameModels\Game\Evo6\Game;
use App\Tools\ResultParsing\WithExtensions;

/**
 * Result parser for the EVO6 system
 *
 * @use WithExtensions<Game>
 */
class ResultsParser extends \Lsr\Lg\Results\LaserMaxx\Evo6\ResultsParser
{

    use WithExtensions;
}
