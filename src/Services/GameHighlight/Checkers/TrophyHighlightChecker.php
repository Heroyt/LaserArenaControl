<?php

namespace App\Services\GameHighlight\Checkers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Player;
use App\GameModels\Game\PlayerTrophy;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Models\DataObjects\Highlights\TrophyHighlight;
use App\Services\GameHighlight\PlayerHighlightChecker;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

/**
 *
 */
class TrophyHighlightChecker implements PlayerHighlightChecker
{
    /**
     * @param  Player  $player
     * @param  HighlightCollection  $highlights
     * @return void
     * @throws GameModeNotFoundException
     * @throws Throwable
     */
    public function checkPlayer(Player $player, HighlightCollection $highlights) : void {
        foreach (PlayerTrophy::SPECIAL_TROPHIES as $trophy) {
            try {
                if ($player->trophy->check($trophy)) {
                    $highlights->add(new TrophyHighlight($trophy, $player, GameHighlight::VERY_HIGH_RARITY));
                }
            } catch (ModelNotFoundException | ValidationException | DirectoryCreationException) {
            }
        }
        foreach (PlayerTrophy::RARE_TROPHIES as $trophy) {
            try {
                if ($player->trophy->check($trophy)) {
                    $rarity = GameHighlight::HIGH_RARITY;
                    switch ($trophy) {
                        case 'favouriteTarget':
                            $rarity = GameHighlight::MEDIUM_RARITY;
                            $rarity += round($player->getHitsPlayer($player->favouriteTarget) / 5);
                            break;
                        case 'favouriteTargetOf':
                            $rarity = GameHighlight::MEDIUM_RARITY;
                            $rarity += round($player->favouriteTargetOf?->getHitsPlayer($player) / 5);
                            break;
                        case 'team-50':
                            $rarity = GameHighlight::MEDIUM_RARITY;
                            $rarity += 50 * min(1.0, ($player->score / $player->team->score));
                            break;
                    }
                    $highlights->add(
                      new TrophyHighlight(
                        $trophy,
                        $player,
                        (int) $rarity
                      )
                    );
                }
            } catch (ModelNotFoundException | ValidationException | DirectoryCreationException) {
            }
        }
    }
}
