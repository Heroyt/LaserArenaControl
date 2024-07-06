<?php

namespace App\Tasks\Payloads;

readonly class GamePrecachePayload
{
    public function __construct(
        public ?string $code = null,
        public ?int    $style = null,
        public ?string $template = null,
    ) {
    }
}
