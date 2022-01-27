<?php

namespace App\Models\Game\GameModes;

use App\Models\Game\Enums\GameModeType;

class CustomSoloMode extends AbstractMode
{

	public GameModeType $type = GameModeType::SOLO;

}