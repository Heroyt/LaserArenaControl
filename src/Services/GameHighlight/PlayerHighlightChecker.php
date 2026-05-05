<?php

namespace App\Services\GameHighlight;

use App\DataObjects\Highlights\HighlightCollection;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;

interface PlayerHighlightChecker
{
    /**
     * Check highlights for a player
     *
     * @template T of Team
     * @template G of Game
     * @template P of Player<G, T>
     *
     * @param  P  $player
     * @param  HighlightCollection  $highlights
     *
     * @return void
     */
    public function checkPlayer(Player $player, HighlightCollection $highlights) : void;
}
