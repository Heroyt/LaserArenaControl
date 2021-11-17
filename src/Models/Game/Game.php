<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Traits\WithPlayers;
use App\Models\Traits\WithTeams;
use DateTimeInterface;
use Dibi\Row;

abstract class Game extends AbstractModel implements InsertExtendInterface
{
	use WithPlayers;
	use WithTeams;

	public const PRIMARY_KEY = 'id_game';
	public const DEFINITION  = [
		'start'   => [],
		'end'     => [],
		'timing'  => [
			'validators' => ['instanceOf:'.Timing::class],
		],
		'code'    => [
			'validators' => [],
		],
		'mode'    => [
			'validators' => ['instanceOf:'.AbstractMode::class],
		],
		'scoring' => [
			'validators' => ['instanceOf:'.Scoring::class],
		],
	];

	public int                $id_game;
	public ?DateTimeInterface $start   = null;
	public ?DateTimeInterface $end     = null;
	public ?Timing            $timing  = null;
	public string             $code;
	public ?AbstractMode      $mode    = null;
	public ?Scoring           $scoring = null;

	public bool $started  = false;
	public bool $finished = false;

	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('start = %dt', $this->start)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		if (empty($this->code)) {
			$this->code = uniqid('g', false);
		}
		return parent::save();
	}

	public static function parseRow(Row $row) : ?InsertExtendInterface {
		return null;
	}

	public function addQueryData(array &$data) : void {
		$data[$this::PRIMARY_KEY] = $this->id;
	}

	public function isStarted() : bool {
		return is_null($this->start);
	}

	public function isFinished() : bool {
		return is_null($this->end);
	}

}