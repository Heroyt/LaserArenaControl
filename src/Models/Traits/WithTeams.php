<?php

namespace App\Models\Traits;

use App\Core\DB;
use App\Models\Game\Game;
use App\Models\Game\Team;
use App\Models\Game\TeamCollection;

trait WithTeams
{

	/** @var Team */
	protected string $teamClass;

	/** @var TeamCollection */
	protected TeamCollection $teams;

	public function addTeam(Team ...$teams) : static {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$this->teams->add(...$teams);
		return $this;
	}


	public function loadTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$className = $this->teamClass;
		$primaryKey = $className::PRIMARY_KEY;
		$rows = DB::select($className::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $this->id)->fetchAll();
		foreach ($rows as $row) {
			/** @var Team $team */
			$team = new $className($row->$primaryKey, $row);
			if ($this instanceof Game) {
				$team->setGame($this);
			}
			$this->teams->set($team, $team->color);
		}
		return $this->teams;
	}

	/**
	 * @return TeamCollection
	 */
	public function getTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->loadTeams();
		}
		return $this->teams;
	}

	public function saveTeams() : bool {
		foreach ($this->teams as $team) {
			if (!$team->save()) {
				return false;
			}
		}
		return true;
	}
}