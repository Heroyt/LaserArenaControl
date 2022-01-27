<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Models\Traits\WithGame;

abstract class Player extends AbstractModel
{
	use WithGame;

	public const PRIMARY_KEY = 'id_player';

	public const DEFINITION = [
		'game'     => [
			'validators' => ['required'],
			'class'      => Game::class,
		],
		'name'     => [
			'validators' => ['required'],
		],
		'score'    => [],
		'vest'     => [],
		'shots'    => [],
		'accuracy' => [],
		'hits'     => [],
		'deaths'   => [],
		'position' => [],
		'team'     => [
			'class' => Team::class,
		],
	];

	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss'];

	public int    $id_player;
	public string $name;
	public int    $score;
	public int    $vest;
	public int    $shots;
	public int    $accuracy;
	public int    $hits;
	public int    $deaths;
	public int    $position;

	/** @var PlayerHit[] */
	public array $hitPlayers = [];

	public int $teamNum;

	protected ?Team $team;

	/**
	 * @return Team|null
	 */
	public function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @param Team $team
	 *
	 * @return Player
	 */
	public function setTeam(Team $team) : Player {
		$this->team = $team;
		return $this;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('id_game = %i && name = %s', $this->game->id, $this->name)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
	}

	/**
	 * @return bool
	 */
	public function saveHits() : bool {
		foreach ($this->hitPlayers as $hits) {
			if (!$hits->save()) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return PlayerHit[]
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getHitsPlayers() : array {
		if (empty($this->hitPlayers)) {
			return $this->loadHits();
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player $player
	 *
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getHitsPlayer(Player $player) : int {
		return $this->getHitsPlayers()[$player->vest]?->count ?? 0;
	}

	/**
	 * @return PlayerHit[]
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function loadHits() : array {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
		foreach ($hits as $row) {
			/** @noinspection PhpParamsInspection */
			$this->addHits($this::get($row->id_target), $row->count);
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player $player
	 * @param int    $count
	 *
	 * @return $this
	 */
	public function addHits(Player $player, int $count) : Player {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$this->hitPlayers[$player->vest] = new $className($this, $player, $count);
		return $this;
	}

	public function getTodayPosition(string $property) : int {
		return 0; // TODO: Implement
	}

	public function getMiss() : int {
		return $this->shots - $this->hits;
	}

	/**
	 * Get a player that this player hit the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getFavouriteTarget() : ?Player {
		$max = 0;
		$maxPlayer = null;
		foreach ($this->getHitsPlayers() as $hits) {
			if ($hits->count > $max) {
				$maxPlayer = $hits->playerTarget;
				$max = $hits->count;
			}
		}
		return $maxPlayer;
	}

	/**
	 * Get a player that hit this player the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getFavouriteTargetOf() : ?Player {
		$max = 0;
		$maxPlayer = null;
		foreach ($this->getGame()->getPlayers() as $player) {
			if ($player->id === $this->id) {
				continue;
			}
			$hits = $player->getHitsPlayer($this);
			if ($hits > $max) {
				$max = $hits;
				$maxPlayer = $player;
			}
		}
		return $maxPlayer;
	}

	/**
	 * @return array{name:string,icon:string}
	 */
	public function getBestAt() : array {
		/** @var array{name:string,icon:string} $fields Best names */
		$fields = [
			'hits'              => [
				'name' => lang('Největší terminátor', context: 'results.bests'),
				'icon' => 'predator',
			],
			'deaths'            => [
				'name' => lang('Objekt největšího zájmu', context: 'results.bests'),
				'icon' => 'skull',
			],
			'score'             => [
				'name' => lang('Absolutní vítěz', context: 'results.bests'),
				'icon' => 'crown',
			],
			'accuracy'          => [
				'name' => lang('Hráč s nejlepší muškou', context: 'results.bests'),
				'icon' => 'target',
			],
			'shots'             => [
				'name' => lang('Nejúspornější střelec', context: 'results.bests'),
				'icon' => 'bullet',
			],
			'miss'              => [
				'name' => lang('Největší mimoň', context: 'results.bests'),
				'icon' => 'bullets',
			],
			'zero-deaths'       => [
				'name' => lang('Nedotknutelný', context: 'results.bests'),
				'icon' => 'shield',
			],
			'100-percent'       => [
				'name' => lang('Sniper', context: 'results.bests'),
				'icon' => 'target',
			],
			'50-percent'        => [
				'name' => lang('Poloviční sniper', context: 'results.bests'),
				'icon' => 'target',
			],
			'5-percent'         => [
				'name' => lang('Občas se i trefí', context: 'results.bests'),
				'icon' => 'target',
			],
			'hitsOwn'           => [
				'name' => lang('Zabiják vlastního týmu', context: 'results.bests'),
				'icon' => 'kill',
			],
			'deathsOwn'         => [
				'name' => lang('Největší vlastňák', context: 'results.bests'),
				'icon' => 'skull',
			],
			'mines'             => [
				'name' => lang('Drtič min', context: 'results.bests'),
				'icon' => 'base_2',
			],
			'average'           => [
				'name' => lang('Hráč', context: 'results.bests'),
				'icon' => 'Vesta',
			],
			'kd-1'              => [
				'name' => lang('Vyrovnaný', context: 'results.bests'),
				'icon' => 'balance',
			],
			'kd-2'              => [
				'name' => lang('Zabiják', context: 'results.bests'),
				'icon' => 'kill',
			],
			'zero'              => [
				'name' => lang('Nula', context: 'results.bests'),
				'icon' => 'zero',
			],
			'team-50'           => [
				'name' => lang('Potahal tým', context: 'results.bests'),
				'icon' => 'star',
			],
			'favouriteTarget'   => [
				'name' => lang('Zasedlý', context: 'results.bests'),
				'icon' => 'death',
			],
			'favouriteTargetOf' => [
				'name' => lang('Pronásledovaný', context: 'results.bests'),
				'icon' => 'death',
			],
		];

		$best = '';
		// Special
		if ($this->accuracy === 100) {
			$best = '100-percent';
		}
		else if ($this->deaths === 0) {
			$best = 'zero-deaths';
		}

		// Classic
		if (empty($best)) {
			foreach ($this::CLASSIC_BESTS as $check) {
				if ($this->getGame()->getBestPlayer($check)->id_player === $this->id_player) {
					$best = $check;
					break;
				}
			}
		}

		// Other
		if (empty($best)) {
			if (($this->score / $this->getTeam()->score) > 0.45) {
				$best = 'team-50';
			}
			else if (abs(($this->hits / $this->deaths) - 1) < 0.1) {
				$best = 'kd-1';
			}
			else if (($this->hits / $this->deaths) > 1.9) {
				$best = 'kd-2';
			}
			else if ($this->accuracy > 50) {
				$best = '50-percent';
			}
			else if ($this->score === 0) {
				$best = 'zero';
			}
			else if ($this->accuracy < 5) {
				$best = 'accuracy';
			}
		}
		if (empty($best)) {
			$favouriteTarget = $this->getFavouriteTarget();
			$favouriteTargetOf = $this->getFavouriteTargetOf();
			if (isset($favouriteTarget) && $this->getHitsPlayer($favouriteTarget) / $this->hits > 0.45) {
				$best = 'favouriteTarget';
			}
			else if (isset($favouriteTargetOf) && $favouriteTargetOf->getHitsPlayer($this) / $favouriteTargetOf->hits > 0.45) {
				$best = 'favouriteTargetOf';
			}
		}

		return $fields[$best] ?? $fields['average'];
	}

}