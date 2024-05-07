<?php

namespace App\Gate\Widgets;

use App\GameModels\Factory\GameFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;

trait WithGameIds
{

    /**
     * @var array<string, int[]>
     */
    protected ?array $gameIds = null;

    public function getGameIds(
      ?DateTimeInterface $dateFrom = null,
      ?DateTimeInterface $dateTo = null,
      ?array             $systems = []
    ) : array {
        if (!isset($this->gameIds)) {
            $dateFrom ??= new DateTimeImmutable();
            $dateTo ??= new DateTimeImmutable();
            $query = GameFactory::queryGames(true)
                                ->where('[start] BETWEEN %d AND %d', $dateFrom, $dateTo);
            if (isset($systems) && count($systems) > 0) {
                $query->where('[system] IN %in', $systems);
            }
            /** @var array<string,Row[]> $games */
            $games = $query->fetchAssoc('system|id_game', cache: false);
            foreach ($games as $system => $systemGames) {
                /** @var array<int, Row> $systemGames */
                $this->gameIds[$system] ??= array_keys($systemGames);
            }
        }
        return $this->gameIds;
    }

    public function setGameIds(array $gameIds) : static {
        $this->gameIds = $gameIds;
        return $this;
    }
}