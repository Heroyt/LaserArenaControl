<?php

namespace App\Api\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(
  schema: 'GamesList',
  type  : 'array',
  items : new OA\Items(
    oneOf: [
             new OA\Schema(
               ref: '#/components/schemas/Game',
             ),
             new OA\Schema(
               type: 'string',
             ),
             new OA\Schema(
               type: 'string',
             ),
           ]
  )
)]
class GamesListResponse
{
}
