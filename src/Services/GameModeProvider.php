<?php

declare(strict_types=1);

namespace App\Services;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\GameModeProviderInterface;
use Lsr\Lg\Results\Interface\Models\GameModeInterface;

class GameModeProvider implements GameModeProviderInterface
{
    public function find(
      string       $name,
      GameModeType $type = GameModeType::TEAM,
      string       $system = ''
    ) : ?GameModeInterface {
        return GameModeFactory::find($name, $type, $system);
    }
}
