<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ResultsScanResult', type: 'object')]
readonly class ResultsScanResult implements JsonSerializable
{
    /**
     * @param list<QueuedResultFileImport> $queuedFiles
     * @param list<ResultsScanError> $errors
     */
    public function __construct(
        #[OA\Property]
        public string $dir,
        #[OA\Property]
        public int    $seen,
        #[OA\Property]
        public int    $queued,
        #[OA\Property]
        public int    $unchanged,
        #[OA\Property]
        public int    $invalid,
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/QueuedResultFileImport'))]
        public array $queuedFiles = [],
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/ResultsScanError'))]
        public array  $errors = [],
    )
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
