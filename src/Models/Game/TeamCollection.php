<?php

namespace App\Models\Game;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\Models\Game\Query\TeamQuery;

class TeamCollection extends AbstractCollection
{

	public string $type = Team::class;

	public function query() : CollectionQueryInterface {
		return new TeamQuery($this);
	}
}