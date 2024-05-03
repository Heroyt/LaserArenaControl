<?php

namespace App\Gate\Settings;

/**
 *
 */
readonly class VestGunAfterGameSettings extends GateSettings
{
    use WithTime;

    public function __construct(
      ?int $time = null,
    ) {
        $this->time = $time;
    }

}