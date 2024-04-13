<?php

namespace App\Gate\Settings;

use App\Core\App;
use Lsr\Core\Config;
use Lsr\Core\Constants;

/**
 *
 */
readonly class VestsSettings extends GateSettings
{

    use WithTime;

    public function __construct(
      ?int $time = null
    ) {
        /** @var Config $config */
        $config = App::getService('config');
        // Get default value from config or deprecated constant
        $this->time = (int) ($time ?? $config->getConfig('ENV')['GAME_LOADED_TIME'] ?? Constants::GAME_LOADED_TIME);
    }

}