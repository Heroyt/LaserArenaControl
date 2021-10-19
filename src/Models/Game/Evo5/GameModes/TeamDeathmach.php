<?php

namespace App\Models\Game\Evo5\GameModes;

use App\Models\Game\GameModes\AbstractMode;

class TeamDeathmach extends AbstractMode
{

	public int    $type        = AbstractMode::TYPE_TEAM;
	public string $name        = 'Team deathmach';
	public string $description = 'Classic team game type.';

}