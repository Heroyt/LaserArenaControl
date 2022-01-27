<?php

namespace App\Models\Game\GameModes;

use App\Models\Game\Enums\GameModeType;

class Deathmach extends AbstractMode
{

	public GameModeType $type        = GameModeType::SOLO;
	public string       $name        = 'Deathmach';
	public ?string      $description = 'Free for all game type.';

}