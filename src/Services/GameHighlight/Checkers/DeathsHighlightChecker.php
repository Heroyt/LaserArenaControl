<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Lasermaxx\Game as LaserMaxxGame;
use App\GameModels\Game\Player;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\PlayerHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Throwable;

/**
 *
 */
class DeathsHighlightChecker implements PlayerHighlightChecker
{
    /**
     * @inheritDoc
     */
    public function checkPlayer(Player $player, HighlightCollection $highlights) : void {
        $name = $player->name;
        $gender = GenderService::rankWord($name);
        try {
            if (
              property_exists($player, 'deathsOwn')
              && property_exists($player, 'deathsOther')
              && $player->deathsOwn > $player->deathsOther
              && $player->game->mode?->isTeam()
            ) {
                $highlights->add(
                  new GameHighlight(
                    GameHighlightType::DEATHS,
                    sprintf(
                      lang(
                                 '%s zasáhlo více spoluhráčů, než protihráčů',
                        context: 'deaths',
                        domain : 'highlights'
                      ),
                      '@'.$name.'@<'.NameInflectionService::genitive($name).'>'
                    ),
                    GameHighlight::VERY_HIGH_RARITY + 20
                  )
                );
            }
        } catch (Throwable) {
            // Ignore
        }

        try {
            if (($game = $player->game) instanceof LaserMaxxGame) {
                $secondsTotal = $player->deaths * $game->respawn;
                $minutes = $secondsTotal / 60;
                $seconds = $secondsTotal % 60;
                $gameLength = $game->getRealGameLength();

                if ($minutes / $gameLength > 0.3) {
                    $highlights->add(
                      new GameHighlight(
                        GameHighlightType::DEATHS,
                        sprintf(
                          lang(
                                     match ($gender) {
                                         Gender::MALE   => '%s strávil %s ve hře vypnutý.',
                                         Gender::FEMALE => '%s strávila %s ve hře vypnutá.',
                                         Gender::OTHER  => '%s strávilo %s ve hře vypnuté.',
                                     }.($minutes / $gameLength > 0.5 ? ' To je víc než polovina hry!' : ''),
                            context: 'deaths',
                            domain : 'highlights'
                          ),
                          '@'.$name.'@',
                          sprintf(
                            lang(
                              '%d minutu',
                              '%d minut',
                              (int) floor($minutes),
                              'trvání'
                            ),
                            floor($minutes)
                          ).
                          (
                          $seconds > 0 ?
                            ' '.lang('a', context: 'spojka').' '.
                            sprintf(
                              lang('%d sekundu', '%d sekund', $seconds, 'trvání'),
                              $seconds
                            )
                            : ''
                          )
                        ),
                        (int) (GameHighlight::MEDIUM_RARITY + round(50 * $minutes / $gameLength))
                      )
                    );
                }
            }
        } catch (Throwable) {
            // Ignore
        }
    }
}
