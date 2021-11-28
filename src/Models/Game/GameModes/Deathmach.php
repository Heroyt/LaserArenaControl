<?php

namespace App\Models\Game\GameModes;

class Deathmach extends AbstractMode
{

	public int     $type        = AbstractMode::TYPE_SOLO;
	public string  $name        = 'Deathmach';
	public ?string $description = 'Free for all game type.';

}