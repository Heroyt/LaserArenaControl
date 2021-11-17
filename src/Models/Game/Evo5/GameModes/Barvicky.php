<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class Barvicky extends AbstractMode
{

	public string $name = 'Barvičky';
	public int    $type = self::TYPE_TEAM;

}