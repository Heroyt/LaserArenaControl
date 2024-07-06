<?php

namespace App\Gate\Settings;

/**
 *
 */
readonly class YoutubeSettings extends GateSettings
{
    use WithTime;

    public function __construct(
        public string          $url,
        public ImageScreenType $screenType = ImageScreenType::CENTER,
        public AnimationType   $animationType = AnimationType::FADE,
        ?int $time = null,
    ) {
        $this->time = $time;
    }
}
