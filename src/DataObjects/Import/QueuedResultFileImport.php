<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

readonly class QueuedResultFileImport
{
    public function __construct(
        public string  $path,
        public string  $pathHash,
        public string  $system,
        public int     $mtime,
        public int     $size,
        public string  $contentHash,
        public string  $version,
        public ?string $content = null,
    )
    {
    }

    public static function fromVersion(ResultFileVersion $version, string $system, ?string $content = null): self
    {
        return new self(
            path: $version->path,
            pathHash: $version->pathHash,
            system: $system,
            mtime: $version->mtime,
            size: $version->size,
            contentHash: $version->contentHash,
            version: $version->version,
            content: $content,
        );
    }
}
