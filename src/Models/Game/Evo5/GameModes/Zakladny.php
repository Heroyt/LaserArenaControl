<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class Zakladny extends AbstractMode
{

	public string $name = 'Základny';
	public int    $type = self::TYPE_TEAM;

}