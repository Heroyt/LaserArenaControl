<?php

namespace App\Services\GameHighlight;

use App\DataObjects\Highlights\HighlightCollection;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;

interface GameHighlightChecker
{
    /**
     * Check highlights for game
     *
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G  $game
     * @param  HighlightCollection  $highlights
     *
     * @return void
     */
    public function checkGame(Game $game, HighlightCollection $highlights) : void;
}
