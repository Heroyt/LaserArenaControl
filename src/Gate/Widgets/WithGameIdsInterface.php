<?php

namespace App\Gate\Widgets;

use DateTimeInterface;

interface WithGameIdsInterface
{
    /**
     * @param  DateTimeInterface|null  $dateFrom
     * @param  DateTimeInterface|null  $dateTo
     * @param  string[]|null  $systems
     * @param  bool  $rankableOnly
     * @return array<string,int[]>
     */
    public function getGameIds(
      ?DateTimeInterface $dateFrom = null,
      ?DateTimeInterface $dateTo = null,
      ?array $systems = [],
      bool   $rankableOnly = false,
    ) : array;

    /**
     * @param  array{
     *     rankable:array<string, int[]>|null,
     *     all:array<string, int[]>|null
     * }|array<string, int[]>|null  $gameIds
     * @return $this
     */
    public function setGameIds(?array $gameIds) : static;
}
