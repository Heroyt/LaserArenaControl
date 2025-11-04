<?php

namespace App\DataObjects\NewGame;

use Lsr\Lg\Results\Enums\GameModeType;

/**
 *
 */
class ModeLoadData
{
    public int $id = 0;
    public string $name = '';
    public GameModeType $type = GameModeType::TEAM;
    /** @var array<string|int,string> */
    public array $variations = [];
}
