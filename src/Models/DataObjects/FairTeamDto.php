<?php

namespace App\Models\DataObjects;

/**
 *
 */
class FairTeamDto
{
    /**
     * @param  int  $key
     * @param  PlayerSkillDto[]  $players
     * @param  int  $skill
     */
    public function __construct(
        public int $key,
        public array $players = [],
        public int $skill = 0,
    ) {
    }
}
