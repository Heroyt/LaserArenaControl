<?php

namespace App\Models\Tournament;

use App\Models\Auth\Player as LigaPlayer;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_player')]
class Player extends Model
{
	use WithPublicId;

	public const TABLE = 'tournament_players';

	public string $nickname;
	public ?string $name = null;
	public ?string $surname = null;

	public PlayerSkill $skill = PlayerSkill::BEGINNER;

	public ?string $image = null;

	public bool $captain = false;
	public bool $sub = false;
	#[Email]
	public ?string $email = null;
	public ?string $phone = null;
	public ?int $birthYear = null;

	#[ManyToOne]
	public Tournament $tournament;
	#[ManyToOne]
	public ?Team       $team = null;
	#[ManyToOne]
	public ?LigaPlayer $user = null;

	public DateTimeInterface  $createdAt;
	public ?DateTimeInterface $updatedAt = null;

	public function insert() : bool {
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
	 * @return string|null
	 */
	public function getImageUrl(): ?string {
		if (empty($this->image)) {
			return null;
		}
		return App::getUrl() . $this->image;
	}

}