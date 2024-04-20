<?php

namespace App\Gate\Settings;

/**
 * @property string $value
 * @method static MusicModeScreenLayout[] cases()
 * @method static MusicModeScreenLayout|null tryFrom(string $param)
 */
enum MusicModeScreenLayout : string
{

    case EMPTY_SPACE = 'empty_space';
    case FULL_SCREEN = 'full_screen';

    public function getReadableName() : string {
        return match ($this) {
            self::EMPTY_SPACE => lang('Ve volném prostoru', context: 'gate.screen.type'),
            self::FULL_SCREEN => lang('Celá obrazovka', context: 'gate.screen.type'),
        };
    }
}
