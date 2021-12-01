<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class Apokalypsa extends AbstractMode
{

	public string $name = 'Apokalypsa';
	public int    $type = self::TYPE_TEAM;

}