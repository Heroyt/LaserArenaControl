<?php

namespace App\Templates\Results;

use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Today;
use Lsr\Core\Controllers\TemplateParameters;

/**
 * @template G of Game
 */
class ResultsParams extends TemplateParameters
{
    /**
     * @param  G  $game
     * @param  PrintStyle  $style
     * @param  PrintTemplate  $template
     * @param  Today  $today
     * @param  non-empty-string  $publicUrl
     * @param  non-empty-string  $qr
     * @param  non-empty-string  $lang
     * @param  int<1,max>  $copies
     * @param  bool  $colorless
     */
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
