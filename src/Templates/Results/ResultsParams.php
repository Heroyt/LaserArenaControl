<?php

namespace App\Templates\Results;

use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Today;
use Lsr\Core\Controllers\TemplateParameters;

class ResultsParams extends TemplateParameters
{
    public function __construct(
      public Game          $game,
      public PrintStyle    $style,
      public PrintTemplate $template,
      public Today         $today,
      public string        $publicUrl,
      public string        $qr,
      public string        $lang,
      public int           $copies = 1,
      public bool          $colorless = false,
    ) {}
}
