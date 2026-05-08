<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

readonly class ResultsScanError
{
    public function __construct(
        public string  $message,
        public ?string $path = null,
        public ?string $system = null,
    )
    {
    }
}
