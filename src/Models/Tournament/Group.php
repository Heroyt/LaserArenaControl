<?php

namespace App\Models\Tournament;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_group')]
class Group extends Model
{
	use WithPublicId;

	public const TABLE = 'tournament_groups';

	public ?string $round = null;
	public string $name;
	#[ManyToOne]
	public Tournament $tournament;


	/** @var Progression[] */
	private array $progressionsFrom = [];

	/** @var Game[] */
	private array $games = [];

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressionsFrom(): array {
		if (empty($this->progressionsFrom)) {
			$this->progressionsFrom = Progression::query()->where('id_group_from = %i', $this->id)->get();
		}
		return $this->progressionsFrom;
	}


	/** @var Progression[] */
	private array $progressionsTo = [];

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressionsTo(): array {
		if (empty($this->progressionsTo)) {
			$this->progressionsTo = Progression::query()->where('id_group_to = %i', $this->id)->get();
		}
		return $this->progressionsTo;
	}

	/**
	 * @return Game[]
	 * @throws ValidationException
	 */
	public function getGames(): array {
		if (empty($this->games)) {
			$this->games = Game::query()->where('[id_group] = %i', $this->id)->get();
		}
		return $this->games;
	}

}