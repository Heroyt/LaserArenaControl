<?php

declare(strict_types=1);

namespace App\Gate\Settings;

readonly class RtspSettings extends GateSettings
{
    /**
     * @param  string[]  $streams
     */
    public function __construct(
        public array $streams = [],
        public int   $maxStreams = 9,
    ) {
    }
}
