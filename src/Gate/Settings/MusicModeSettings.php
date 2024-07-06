<?php

namespace App\Gate\Settings;

readonly class MusicModeSettings extends GateSettings
{
    public function __construct(
        public MusicModeScreenLayout $layout = MusicModeScreenLayout::EMPTY_SPACE,
    ) {
    }
}
