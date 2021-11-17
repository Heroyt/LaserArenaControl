<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class CSGO extends AbstractMode
{

	public string $name = 'CSGO';
	public int    $type = self::TYPE_TEAM;

}