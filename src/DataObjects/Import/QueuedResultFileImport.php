<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'QueuedResultFileImport', type: 'object')]
readonly class QueuedResultFileImport
{
    public function __construct(
        #[OA\Property]
        public string  $path,
        #[OA\Property]
        public string  $pathHash,
        #[OA\Property]
        public string  $system,
        #[OA\Property]
        public int     $mtime,
        #[OA\Property]
        public int     $size,
        #[OA\Property]
        public string  $contentHash,
        #[OA\Property]
        public string  $version,
        #[OA\Property(nullable: true)]
        public ?string $content = null,
    ) {
    }

    public static function fromVersion(ResultFileVersion $version, string $system, ?string $content = null): self {
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
