<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

readonly class ResultsScanResult
{
    /**
     * @param list<ResultsScanError> $errors
     */
    public function __construct(
        public string $dir,
        public int    $seen,
        public int    $queued,
        public int    $unchanged,
        public int    $invalid,
        public array  $errors = [],
    )
    {
    }
}
