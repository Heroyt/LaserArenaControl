<?php

namespace App\Gate\Logic;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;

/**
 * @method static ScreenTriggerType[] cases()
 * @method static ScreenTriggerType from(string $value)
 * @method static ScreenTriggerType|null tryFrom(string $value)
 * @property string $value
 */
enum ScreenTriggerType : string
{
    case DEFAULT      = 'default';
    case GAME_LOADED  = 'game_loaded';
    case GAME_PLAYING = 'game_playing';
    case GAME_ENDED   = 'game_ended';
    case RESULTS_MANUAL = 'results_manual';
    case CUSTOM       = 'custom';

    /**
     * Get game timestamp from which the gate counter should start.
     *
     * @template T of Team
     * @template P of Player
     * @param  Game<T,P>  $game
     * @return int -1 if invalid, or UNIX timestamp
     */
    public function getReloadTimeFrom(Game $game) : int {
        return match ($this) {
            self::GAME_LOADED                      => $game->fileTime?->getTimestamp() ?? -1,
            self::GAME_PLAYING                     => $game->start?->getTimestamp() ?? -1,
            self::GAME_ENDED, self::RESULTS_MANUAL => $game->end?->getTimestamp() ?? -1,
            default                                => -1,
        };
    }

    /**
     * @return bool
     */
    public function isReloadTimeSettable() : bool {
        return match ($this) {
            self::GAME_LOADED, self::GAME_PLAYING, self::GAME_ENDED, self::RESULTS_MANUAL => true,
            default                                                                       => false,
        };
    }

    public function getReadable() : string {
        return match ($this) {
            self::DEFAULT        => lang('Výchozí', context: 'screen.trigger', domain: 'gate'),
            self::GAME_LOADED    => lang('Hra načtena', context: 'screen.trigger', domain: 'gate'),
            self::GAME_PLAYING   => lang('Hra hraje', context: 'screen.trigger', domain: 'gate'),
            self::GAME_ENDED     => lang('Hra skončila', context: 'screen.trigger', domain: 'gate'),
            self::RESULTS_MANUAL => lang('Manuální zobrazení hry', context: 'screen.trigger', domain: 'gate'),
            self::CUSTOM         => lang('Vlastní událost', context: 'screen.trigger', domain: 'gate'),
        };
    }

    public function getDescription() : string {
        return match ($this) {
            self::DEFAULT        => lang(
                       'Obrazovka se zobrazuje jako výchozí, pokud žádná jiná před ní nesplňuje podmínku.',
              context: 'screen.trigger',
              domain : 'gate'
            ),
            self::GAME_LOADED    => lang(
                       'Obrazovka se zobrazí, pokud je poslední hra načtená, ale nespuštěná.',
              context: 'screen.trigger',
              domain : 'gate'
            ),
            self::GAME_PLAYING   => lang(
                       'Obrazovka se zobrazí, pokud poslední hra právě probíhá.',
              context: 'screen.trigger',
              domain : 'gate'
            ),
            self::GAME_ENDED     => lang(
                       'Obrazovka se zobrazí, pokud poseldní hra už skončila.',
              context: 'screen.trigger',
              domain : 'gate'
            ),
            self::RESULTS_MANUAL => lang(
                       'Obrazovka se zobrazí, pokud je hra manuálně zobrazena (zobrazit na výsledkové tabuli).',
              context: 'screen.trigger',
              domain : 'gate'
            ),
            self::CUSTOM         => lang(
                       'Obrazovka se zobrazí, pokud se vyvolá nějaká manuální událost.',
              context: 'screen.trigger',
              domain : 'gate'
            ),
        };
    }
}
