<?php

namespace App\Models\DataObjects;

use App\Models\Group\Player;

/**
 *
 */
class PlayerSkillDto
{
    public ?int $id = null;
    public ?string $code = null;

    public function __construct(
        public string $name,
        public int $skill,
    ) {
    }

    public static function fromGroupPlayer(Player $player): PlayerSkillDto {
        $dto = new self($player->name, $player->getSkill());
        $dto->code = $player->player->user?->getCode();
        return $dto;
    }

    public static function fromGamePlayer(\App\GameModels\Game\Player $player): PlayerSkillDto {
        $dto = new self($player->name, $player->getSkill());
        $dto->code = $player->user?->getCode();
        return $dto;
    }
}
