<?php

namespace App\Tasks\Payloads;

readonly class GameImportPayload
{
    public function __construct(
        public string $dir,
    ) {
    }
}
