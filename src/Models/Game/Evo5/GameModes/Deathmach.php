<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class Deathmach extends AbstractMode
{

	public int    $type        = AbstractMode::TYPE_SOLO;
	public string $name        = 'Deathmach';
	public string $description = 'Free for all game type.';

}