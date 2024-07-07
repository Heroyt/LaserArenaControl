<?php

namespace App\Tools\GameLoading;

/**
 *
 */
class LasermaxxLoadTeamData
{
    public function __construct(
        public int $key,
        public string $name,
        public int $playerCount = 0,
    ) {
    }
}
