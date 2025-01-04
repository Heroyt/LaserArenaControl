<?php

namespace App\Models;

use App\GameModels\Game\GameModes\AbstractMode;

class GameModeVariationValue
{
    public function __construct(
      public GameModeVariation $variation,
      public AbstractMode      $mode,
      public string            $value,
      public string            $suffix,
      public int               $order = 0,
    ) {}
}
