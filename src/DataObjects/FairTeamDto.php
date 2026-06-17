<?php

declare(strict_types=1);

namespace App\DataObjects;

class FairTeamDto
{
    /**
     * @param  int  $key
     * @param  PlayerSkillDto[]  $players
     * @param  int  $skill
     */
    public function __construct(
        public int   $key,
        public array $players = [],
        public int   $skill = 0,
    ) {
    }
}
