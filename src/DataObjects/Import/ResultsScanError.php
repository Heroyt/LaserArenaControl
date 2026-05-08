<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ResultsScanError', type: 'object')]
readonly class ResultsScanError
{
    public function __construct(
        #[OA\Property]
        public string  $message,
        #[OA\Property(nullable: true)]
        public ?string $path = null,
        #[OA\Property(nullable: true)]
        public ?string $system = null,
    )
    {
    }
}
