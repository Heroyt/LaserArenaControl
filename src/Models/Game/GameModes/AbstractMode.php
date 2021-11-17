<?php

namespace App\Models\Game\GameModes;

use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\GameModeNotFoundException;
use App\Models\Factory\GameModeFactory;
use Dibi\Row;
use InvalidArgumentException;

abstract class AbstractMode implements InsertExtendInterface
{

	public const TABLE = 'game_modes';

	public const TYPE_SOLO = 0;
	public const TYPE_TEAM = 1;

	public string $name        = '';
	public string $description = '';
	public int    $type        = self::TYPE_TEAM;

	public function __construct(public ?int $id = null) {
		if (isset($this->id)) {
			/** @var object{id_mode:int,name:string,description:string,type:string}|null $mode */
			$mode = DB::select($this::TABLE, '*')->where('id_mode = %i', $this->id)->fetch();
			if (!isset($mode)) {
				throw new InvalidArgumentException('Game mode with id "'.$this->id.'" does not exist.');
			}
			$this->name = $mode->name;
			$this->description = $mode->description ?? '';
			$this->type = $mode->type === 'TEAM' ? $this::TYPE_TEAM : $this::TYPE_SOLO;
		}
	}

	/**
	 * @param Row $row
	 *
	 * @return InsertExtendInterface
	 * @throws GameModeNotFoundException
	 */
	public static function parseRow(Row $row) : InsertExtendInterface {
		return GameModeFactory::getById($row->id_mode ?? 0);
	}

	public function addQueryData(array &$data) : void {
		$data['id_mode'] = $this->id;
	}

	public function isTeam() : bool {
		return $this->type === self::TYPE_TEAM;
	}

	public function isSolo() : bool {
		return $this->type === self::TYPE_SOLO;
	}


}