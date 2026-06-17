<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

readonly class ResultFileVersion
{
    public function __construct(
        public string $path,
        public string $pathHash,
        public int    $mtime,
        public int    $size,
        public string $contentHash,
        public string $version,
    ) {
    }
}
