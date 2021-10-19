<?php

namespace App\Models\Game\Evo5;

class Player extends \App\Models\Game\Player
{

	public int   $shotPoints  = 0;
	public int   $scoreBonus  = 0;
	public int   $scorePowers = 0;
	public int   $scoreMines  = 0;
	public int   $ammoRest    = 0;
	public int   $minesHits   = 0;
	public array $bonus       = [
		'agent'        => 0,
		'invisibility' => 0,
		'machineGun'   => 0,
		'shield'       => 0,
	];
	public int   $hitsOther   = 0;
	public int   $hitsOwn     = 0;
	public int   $deathsOwn   = 0;
	public int   $deathsOther = 0;

}