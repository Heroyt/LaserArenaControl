<?php

namespace App\Models\Tournament;

use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_team')]
class Team extends Model
{
	use WithPublicId;

	public const TABLE = 'tournament_teams';

	public string $name;

	public ?string $image = null;

	public int $points = 0;

	#[ManyToOne]
	public Tournament $tournament;
	public DateTimeInterface $createdAt;
	public ?DateTimeInterface $updatedAt = null;
	/** @var Player[] */
	private array $players = [];

	private int $score;
	private int $wins;
	private int $draws;
	private int $losses;
	/**
	 * @var array<int,int>
	 */
	private array $keys;

	public function getScore(): int {
		if (!isset($this->score)) {
			$this->score = DB::select(GameTeam::TABLE, 'SUM([score])')->where('[id_team] = %i', $this->id)->fetchSingle(false) ?? 0;
		}
		return $this->score;
	}

	public function insert(): bool {
		if (!isset($this->createdAt)) {
			$this->createdAt = new DateTimeImmutable();
		}
		return parent::insert();
	}

	public function update(): bool {
		$this->updatedAt = new DateTimeImmutable();
		return parent::update();
	}

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_team = %i', $this->id)->get();
		}
		return $this->players;
	}

	/**
	 * @return string|null
	 */
	public function getImageUrl(): ?string {
		if (empty($this->image)) {
			return null;
		}
		return $this->image;
	}

	/**
	 * @return int
	 */
	public function getWins(): int {
		if (!isset($this->wins)) {
			$this->wins = DB::select(GameTeam::TABLE, 'COUNT(*)')->where('[id_team] = %i AND [points] = %i', $this->id, $this->tournament->points->win)->fetchSingle(false) ?? 0;
		}
		return $this->wins;
	}

	/**
	 * @return int
	 */
	public function getLosses(): int {
		if (!isset($this->losses)) {
			$this->losses = DB::select(GameTeam::TABLE, 'COUNT(*)')->where('[id_team] = %i AND [points] = %i', $this->id, $this->tournament->points->loss)->fetchSingle(false) ?? 0;
		}
		return $this->losses;
	}

	/**
	 * @return int
	 */
	public function getDraws(): int {
		if (!isset($this->draws)) {
			$this->draws = DB::select(GameTeam::TABLE, 'COUNT(*)')->where('[id_team] = %i AND [points] = %i', $this->id, $this->tournament->points->draw)->fetchSingle(false) ?? 0;
		}
		return $this->draws;
	}

	/**
	 * @return array<int,int>
	 */
	public function getGroupKeys(): array {
		$this->keys ??= DB::select([GameTeam::TABLE, 'a'], 'b.id_group, a.key')->join(Game::TABLE, 'b')->on('a.id_game = b.id_game')->where('a.id_team = %i', $this->id)->groupBy('id_group')->fetchPairs('id_group', 'key', false);
		return $this->keys;
	}

}