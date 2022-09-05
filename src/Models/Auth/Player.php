<?php

namespace App\Models\Auth;

use App\Models\Auth\Validators\PlayerCode;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_user')]
class Player extends Model
{

	public const TABLE = 'players';

	/** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
	#[PlayerCode]
	public string $code;
	public string $nickname;
	#[Email]
	public string $email;

	/**
	 * @param string $code
	 * @param Player $player
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, Player $player) : void {
		if (!$player->validateUniqueCode($code)) {
			throw new ValidationException('Invalid player\'s code. Must be unique.');
		}
	}

	/**
	 * Validate the unique player's code to be unique for all player in one arena
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public function validateUniqueCode(string $code) : bool {
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * @return string
	 */
	public function getCode() : string {
		return $this->code;
	}
}