<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\ResultParsing\Evo6;

use App\GameModels\Game\Evo6\Game;
use App\Models\Auth\Player;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Tools\ResultParsing\WithExtensions;

/**
 * Result parser for the EVO6 system
 *
 * @use WithExtensions<Game>
 */
class ResultsParser extends \Lsr\Lg\Results\LaserMaxx\Evo6\ResultsParser
{
    use WithExtensions;

    public const string  MUSIC_CLASS = MusicMode::class;
    public const string GAME_GROUP_CLASS = GameGroup::class;
    public const string USER_CLASS = Player::class;
}
