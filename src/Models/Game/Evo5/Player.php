<?php

namespace App\Models\Game\Evo5;

use App\Models\Game\Game;

class Player extends \App\Models\Game\Player
{

	public const TABLE      = 'evo5_players';
	public const DEFINITION = [
		'game'        => [
			'validators' => ['required'],
			'class'      => Game::class,
		],
		'name'        => [
			'validators' => ['required'],
		],
		'score'       => ['default' => 0],
		'vest'        => [],
		'shots'       => ['default' => 0],
		'accuracy'    => ['default' => 0],
		'hits'        => ['default' => 0],
		'deaths'      => ['default' => 0],
		'position'    => ['default' => 0],
		'team'        => [
			'class' => Team::class,
		],
		'shotPoints'  => [],
		'scoreBonus'  => [],
		'scorePowers' => [],
		'scoreMines'  => [],
		'ammoRest'    => [],
		'hitsOther'   => [],
		'hitsOwn'     => [],
		'deathsOther' => [],
		'deathsOwn'   => [],
		'bonus'       => ['class' => BonusCounts::class, 'initialize' => true],
	];
	public int         $shotPoints  = 0;
	public int         $scoreBonus  = 0;
	public int         $scorePowers = 0;
	public int         $scoreMines  = 0;
	public int         $ammoRest    = 0;
	public int         $minesHits   = 0;
	public BonusCounts $bonus;
	public int         $hitsOther   = 0;
	public int         $hitsOwn     = 0;
	public int         $deathsOwn   = 0;
	public int         $deathsOther = 0;

	public function getMines() : int {
		return $this->bonus->getSum();
	}

	public function getRemainingLives() : int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
	}

}