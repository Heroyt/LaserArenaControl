<?php

namespace App\Tasks\Payloads;

readonly class GameHighlightsPayload
{
    public function __construct(
        public ?string $code = null,
    ) {
    }
}
