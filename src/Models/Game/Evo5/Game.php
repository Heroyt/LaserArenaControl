<?php

namespace App\Models\Game\Evo5;

use App\Models\Game\GameModes\AbstractMode;
use App\Models\Game\Scoring;
use App\Models\Game\Timing;

class Game extends \App\Models\Game\Game
{

	public const TABLE      = 'evo5_games';
	public const DEFINITION = [
		'fileNumber' => [],
		'modeName'   => [],
		'start'      => [],
		'end'        => [],
		'timing'     => [
			'validators' => ['instanceOf:'.Timing::class],
			'class'      => Timing::class,
		],
		'code'       => [
			'validators' => [],
		],
		'mode'       => [
			'validators' => ['instanceOf:'.AbstractMode::class],
			'class'      => AbstractMode::class,
		],
		'scoring'    => [
			'validators' => ['instanceOf:'.Scoring::class],
			'class'      => Scoring::class,
		],
	];

	public int    $fileNumber;
	public string $modeName;

	protected string $playerClass = Player::class;
	protected string $teamClass   = Team::class;

	public function insert() : bool {
		$this->logger->info('Inserting game: '.$this->fileNumber);
		return parent::insert();
	}

	public function save() : bool {
		return parent::save() && $this->saveTeams() && $this->savePlayers();
	}
}