<?php

namespace App\Models\Traits;

use App\Models\Game\Team;
use App\Models\Game\TeamCollection;

trait WithTeams
{


	/** @var TeamCollection */
	protected TeamCollection $teams;

	public function addTeam(Team ...$teams) : static {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$this->teams->add(...$teams);
		return $this;
	}

	/**
	 * @return TeamCollection
	 */
	public function getTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		return $this->teams;
	}
}