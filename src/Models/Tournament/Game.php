<?php

namespace App\Models\Tournament;

use App\GameModels\Factory\GameFactory;
use DateTimeInterface;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_game')]
class Game extends Model
{
	use WithPublicId;

	public const TABLE = 'tournament_games';

	#[ManyToOne]
	public Tournament $tournament;

	#[ManyToOne]
	public ?Group $group;

	/** @var Player[] */
	#[ManyToMany('tournament_game_players', class: Player::class)]
	public array $players = [];

	/** @var GameTeam[] */
	#[OneToMany(class: GameTeam::class)]
	public array $teams = [];

	public ?string $code = null;
	public DateTimeInterface $start;
	private ?\App\GameModels\Game\Game $game = null;
	private ?Game $nextGame = null;
	private ?Game $prevGame = null;

	public function save(): bool {
		$success = parent::save();
		foreach ($this->teams as $team) {
			$team->save();
		}
		foreach ($this->players as $player) {
			$player->save();
		}
		return $success;
	}

	public function getNextGame(): ?Game {
		if (!isset($this->nextGame)) {
			$this->nextGame = $this->tournament->queryGames()->where('[start] > %dt', $this->start)->orderBy('start')->first();
		}
		return $this->nextGame;
	}

	public function getPrevGame(): ?Game {
		if (!isset($this->prevGame)) {
			$this->prevGame = $this->tournament->queryGames()->where('[start] < %dt', $this->start)->orderBy('start')->desc()->first();
		}
		return $this->prevGame;
	}

	public function getGame(): ?\App\GameModels\Game\Game {
		if (!isset($this->code)) {
			return null;
		}
		$this->game = GameFactory::getByCode($this->code);
		return $this->game;
	}

}