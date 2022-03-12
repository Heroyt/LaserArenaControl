<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Traits\WithPlayers;
use App\Models\Traits\WithTeams;
use App\Tools\Strings;
use DateTimeInterface;
use Dibi\Row;

abstract class Game extends AbstractModel implements InsertExtendInterface
{
	use WithPlayers;
	use WithTeams;

	public const SYSTEM      = '';
	public const PRIMARY_KEY = 'id_game';
	public const DEFINITION  = [
		'fileTime' => [],
		'start'    => [],
		'end'      => [],
		'timing'   => [
			'validators' => ['instanceOf:'.Timing::class],
		],
		'code'     => [
			'validators' => [],
		],
		'mode'     => [
			'validators' => ['instanceOf:'.AbstractMode::class],
		],
		'scoring'  => [
			'validators' => ['instanceOf:'.Scoring::class],
		],
	];

	public int                $id_game;
	public ?DateTimeInterface $fileTime = null;
	public ?DateTimeInterface $start    = null;
	public ?DateTimeInterface $end      = null;
	public ?Timing            $timing   = null;
	public string             $code;
	public ?AbstractMode      $mode     = null;
	public ?Scoring           $scoring  = null;

	public bool $started  = false;
	public bool $finished = false;

	public static function parseRow(Row $row) : ?InsertExtendInterface {
		if (isset($row->id_game, static::$instances[static::TABLE][$row->id_game])) {
			return static::$instances[static::TABLE][$row->id_game];
		}
		return null;
	}

	public static function getTeamColors() : array {
		return [];
	}

	public function save() : bool {
		$pk = $this::PRIMARY_KEY;
		/** @var object{id_game:int,code:string|null}|null $test */
		$test = DB::select($this::TABLE, $pk.', code')->where('start = %dt', $this->start)->fetch();
		if (isset($test)) {
			$this->id = $test->$pk;
			$this->code = $test->code;
		}
		if (empty($this->code)) {
			$this->code = uniqid('g', false);
		}
		return parent::save();
	}

	public function addQueryData(array &$data) : void {
		$data[$this::PRIMARY_KEY] = $this->id;
	}

	public function isStarted() : bool {
		return !is_null($this->start);
	}

	public function isFinished() : bool {
		return !is_null($this->end);
	}

	/**
	 * @param string $property
	 *
	 * @return Player|null
	 */
	public function getBestPlayer(string $property) : ?Player {
		$query = $this->getPlayers()->query()->sortBy($property);
		switch ($property) {
			case 'shots':
				$query->asc();
				break;
			default:
				$query->desc();
				break;
		}
		return $query->first();
	}

	/**
	 * @return array<string,string>
	 */
	public function getBestsFields() : array {
		$fields = [
			'hits'     => lang('Největší terminátor', context: 'results.bests'),
			'deaths'   => lang('Objekt největšího zájmu', context: 'results.bests'),
			'score'    => lang('Absolutní vítěz', context: 'results.bests'),
			'accuracy' => lang('Hráč s nejlepší muškou', context: 'results.bests'),
			'shots'    => lang('Nejúspornější střelec', context: 'results.bests'),
			'miss'     => lang('Největší mimoň', context: 'results.bests'),
		];
		foreach ($fields as $key => $value) {
			$settingName = Strings::toCamelCase('best_'.$key);
			if (!($this->mode->settings->$settingName ?? true)) {
				unset($fields[$key]);
			}
		}
		return $fields;
	}

}