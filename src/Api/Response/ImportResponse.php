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
        #[OA\Property(
            items: new OA\Items(
                oneOf: [
                new OA\Schema(
                    properties: [
                    'error' => new OA\Property(
                        property: 'error',
                        type: 'string',
                        nullable: true,
                    ),
                    'exception' => new OA\Property(
                        property: 'exception',
                        type: 'string',
                        nullable: true,
                    ),
                    'sql' => new OA\Property(
                        property: 'sql',
                        type: 'string',
                        nullable: true,
                    ),
                      ],
                    type      : 'object'
                ),
                new OA\Schema(type: 'string'),
                ]
            )
        )]
        public array $errors,
    ) {
    }

    /**
     * @inheritDoc
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
