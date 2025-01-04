<?php

namespace App\Api\Response\Results;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

/**
 *
 */
#[Schema(type: 'object')]
readonly class LastResultsResponse
{
    /**
     * @param  string[]  $files
     * @param  string  $contents1
     * @param  string  $contents2
     */
    public function __construct(
      #[Property(type: 'array', items: new Items(type: 'string'))]
      public array  $files,
      #[Property]
      public string $contents1,
      #[Property]
      public string $contents2,
    ) {}
}
