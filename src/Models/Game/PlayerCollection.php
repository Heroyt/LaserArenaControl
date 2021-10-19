<?php

namespace App\Models\Game;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\Models\Game\Query\PlayerQuery;

/**
 * @property Player[] $data
 */
class PlayerCollection extends AbstractCollection
{

	protected string $type = Player::class;

	/**
	 * @return CollectionQueryInterface
	 */
	public function query() : CollectionQueryInterface {
		return new PlayerQuery($this);
	}
}