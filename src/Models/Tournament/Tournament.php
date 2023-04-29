<?php

namespace App\Models\Tournament;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\GameGroup;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Core\Models\ModelQuery;

#[PrimaryKey('id_tournament')]
class Tournament extends Model
{
	use WithPublicId;

	public const TABLE = 'tournaments';

	#[ManyToOne]
	public ?GameGroup $group = null;

	#[ManyToOne]
	public ?League $league = null;

	/** @var Group[] */
	#[OneToMany(class: Group::class)]
	public array $groups = [];

	public string $name;
	public ?string $description = null;

	public ?string $image = null;
	public GameModeType $format = GameModeType::TEAM;
	public int $teamSize = 1;
	public int $subCount = 0;

	#[Instantiate]
	public TournamentPoints $points;

	public int $gameLength = 15;
	public int $gamePause = 5;

	public bool $active = true;

	public DateTimeInterface $start;
	public ?DateTimeInterface $end = null;

	/** @var Team[] */
	private array $teams = [];
	/** @var Player[] */
	private array $players = [];
	/** @var Game[] */
	private array $games = [];
	/** @var Progression[] */
	private array $progressions = [];

	public function getImageUrl(): ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getUrl() . $this->image;
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getTeams(): array {
		if ($this->format === GameModeType::SOLO) {
			return [];
		}
		if (empty($this->teams)) {
			$this->teams = Team::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->teams;
	}

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if ($this->format === GameModeType::TEAM) {
			return [];
		}
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->players;
	}

	public function clearGames(): void {
		foreach ($this->getGames() as $game) {
			$game->delete();
		}
		$this->games = [];
	}

	/**
	 * @return Game[]
	 * @throws ValidationException
	 */
	public function getGames(): array {
		if (empty($this->games)) {
			$this->games = $this->queryGames()->get();
		}
		return $this->games;
	}

	/**
	 * @return ModelQuery<Game>
	 */
	public function queryGames(): ModelQuery {
		return Game::query()->where('id_tournament = %i', $this->id);
	}

	public function clearGroups(): void {
		foreach ($this->groups as $group) {
			$group->delete();
		}
		$this->groups = [];
	}

	public function clearProgressions(): void {
		foreach ($this->getProgressions() as $progression) {
			$progression->delete();
		}
		$this->progressions = [];
	}

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressions(): array {
		if (empty($this->progressions)) {
			$this->progressions = Progression::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->progressions;
	}

	public function getPlannedGame(): ?Game {
		$game = $this->queryGames()->where('[code] IS NULL')->orderBy('start')->first();
		return $game ?? $this->queryGames()->orderBy('start')->first();
	}

	/**
	 * @return GameGroup
	 * @throws ValidationException
	 */
	public function getGroup(): GameGroup {
		if (!isset($this->group)) {
			$this->group = new GameGroup();
			$this->group->name = $this->name;
			$this->group->active = false;
			$this->group->save();
		}
		return $this->group;
	}

}