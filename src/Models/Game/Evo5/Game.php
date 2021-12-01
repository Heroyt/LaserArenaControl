<?php

namespace App\Models\Game\Evo5;

use App\Models\Game\GameModes\AbstractMode;
use App\Models\Game\Scoring;
use App\Models\Game\Timing;

class Game extends \App\Models\Game\Game
{

	public const SYSTEM     = 'evo5';
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
		'lives'      => [],
		'ammo'       => [],
		'respawn'    => [],
	];

	public int    $fileNumber;
	public string $modeName;
	/** @var int Initial lives */
	public int $lives = 9999;
	/** @var int Initial ammo count */
	public int $ammo = 9999;
	/** @var int Respawn time in seconds */
	public int $respawn = 5;

	protected string $playerClass = Player::class;
	protected string $teamClass   = Team::class;
	protected bool   $minesOn;

	public function insert() : bool {
		$this->logger->info('Inserting game: '.$this->fileNumber);
		return parent::insert();
	}

	public function save() : bool {
		return parent::save() && $this->saveTeams() && $this->savePlayers();
	}

	/**
	 * Check if mines were enabled
	 *
	 * Checks players until it finds one with some mine-related scores.
	 *
	 * @return bool
	 */
	public function isMinesOn() : bool {
		if (!isset($this->minesOn)) {
			$this->minesOn = false;
			/** @var Player $player */
			foreach ($this->getPlayers() as $player) {
				if ($player->minesHits !== 0 || $player->scoreMines !== 0 || $player->bonus->getSum() > 0) {
					$this->minesOn = true;
					break;
				}
			}
		}
		return $this->minesOn;
	}

	public function getBestsFields() : array {
		$info = parent::getBestsFields();
		if ($this->mode->isTeam()) {
			if ($this->mode->settings->bestHitsOwn) {
				$info['hitsOwn'] = lang('Zabiják vlastního týmu', context: 'results.bests');
			}
			if ($this->mode->settings->bestDeathsOwn) {
				$info['deathsOwn'] = lang('Největší vlastňák', context: 'results.bests');
			}
		}
		if ($this->mode->settings->bestMines && $this->mode->settings->mines && $this->isMinesOn()) {
			$info['mines'] = lang('Drtič min', context: 'results.bests');
		}
		return $info;
	}

	public function getTeamColors() : array {
		return [
			0 => '#f00',
			1 => '#0F0',
			2 => '#00f',
			3 => '#ffc0cb',
			4 => '#ff0',
			5 => '#0ff',
		];
	}
}