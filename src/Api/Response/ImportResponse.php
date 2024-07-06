<?php

namespace App\Api\Response;

use JsonSerializable;
use OpenApi\Attributes as OA;

/**
 *
 */
#[OA\Schema(schema: 'ImportResponse', type: 'object')]
readonly class ImportResponse implements JsonSerializable
{
    /**
     * @param int                                                           $imported
     * @param int                                                           $total
     * @param float                                                         $time
     * @param array{error?:string,exception?:string,sql?:string}[]|string[] $errors
     */
    public function __construct(
        #[OA\Property]
        public int   $imported,
        #[OA\Property]
        public int   $total,
        #[OA\Property]
        public float $time,
        #[OA\Property]
        public array $errors,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
