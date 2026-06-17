<?php

declare(strict_types=1);

namespace App\Gate\Logic;

readonly class CustomEventDto
{
    public int $time;

    public function __construct(
        public string $event,
        ?int          $time = null,
    ) {
        $this->time = $time ?? time();
    }
}
