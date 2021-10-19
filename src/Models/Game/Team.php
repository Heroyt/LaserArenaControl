<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Models\Traits\WithGame;
use App\Models\Traits\WithPlayers;

abstract class Team extends AbstractModel
{
	use WithPlayers;
	use WithGame;

	public int    $id;
	public int    $color;
	public int    $score;
	public int    $position;
	public string $name;

}