<?php

namespace App\Gate\Widgets;

use DateTimeInterface;

interface WithGameIdsInterface
{

    /**
     * @param  DateTimeInterface|null  $dateFrom
     * @param  DateTimeInterface|null  $dateTo
     * @param  string[]|null  $systems
     * @return array<string,int[]>
     */
    public function getGameIds(
      ?DateTimeInterface $dateFrom = null,
      ?DateTimeInterface $dateTo = null,
      ?array             $systems = []
    ) : array;

    /**
     * @param  array<string, int[]>  $gameIds
     * @return $this
     */
    public function setGameIds(array $gameIds) : static;

}