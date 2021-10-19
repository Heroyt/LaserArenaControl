<?php

namespace App\Models\Game\GameModes;

use App\Core\DB;
use InvalidArgumentException;

abstract class AbstractMode
{

	public const TYPE_SOLO = 0;
	public const TYPE_TEAM = 1;

	public string $name        = '';
	public string $description = '';
	public int    $type        = self::TYPE_TEAM;

	public function __construct(public ?int $id = null) {
		if (isset($this->id)) {
			/** @var object{id_mode:int,name:string,description:string,type:string}|null $mode */
			$mode = DB::select('::modes', '*')->where('id_mode = %i', $this->id)->fetch();
			if (!isset($mode)) {
				throw new InvalidArgumentException('Game mode with id "'.$this->id.'" does not exist.');
			}
			$this->name = $mode->name;
			$this->description = $mode->description;
			$this->type = $mode->type === 'TEAM' ? $this::TYPE_TEAM : $this::TYPE_SOLO;
		}
	}

	public function isTeam() : bool {
		return $this->type === self::TYPE_TEAM;
	}

	public function isSolo() : bool {
		return $this->type === self::TYPE_SOLO;
	}


}