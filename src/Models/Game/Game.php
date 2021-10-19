<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Traits\WithPlayers;
use App\Models\Traits\WithTeams;
use DateTime;

abstract class Game extends AbstractModel
{
	use WithPlayers;
	use WithTeams;

	public int           $id;
	public ?DateTime     $start   = null;
	public ?DateTime     $end     = null;
	public ?Timing       $timing  = null;
	public string        $code;
	public ?AbstractMode $mode    = null;
	public ?Scoring      $scoring = null;

	public bool $started  = false;
	public bool $finished = false;

}