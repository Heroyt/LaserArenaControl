<?php

declare(strict_types=1);

namespace App\CQRS\Queries\Games;

use App\DataObjects\Db\Games\MinimalGameRow;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Dibi\Exception;
use Lsr\CQRS\QueryInterface;
use Throwable;

class GameListQuery implements QueryInterface
{
    use BaseGameQuery;

    /**
     * @return Game[]
     * @throws Exception
     * @throws Throwable
     */
    public function get() : array {
        $rows = $this->query->fetchAllDto(MinimalGameRow::class, cache: $this->cache);
        return array_filter(array_map(static fn(MinimalGameRow $row) => GameFactory::getByCode($row->code), $rows));
    }
}
