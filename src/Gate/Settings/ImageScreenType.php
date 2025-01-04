<?php

namespace App\Gate\Settings;

/**
 * @property string $value
 * @method static ImageScreenType[] cases()
 * @method static ImageScreenType from(string $value)
 * @method static ImageScreenType|null tryFrom(string $value)
 */
enum ImageScreenType : string
{
    case CENTER = 'center';
    case FULLSCREEN = 'fullscreen';

    public function getReadableName() : string {
        return match ($this) {
            self::CENTER     => lang('V prostředku', domain: 'gate', context: 'screen.type'),
            self::FULLSCREEN => lang('Celá obrazovka', domain: 'gate', context: 'screen.type'),
        };
    }
}
