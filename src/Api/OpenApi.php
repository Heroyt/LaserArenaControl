<?php

namespace App\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version    : '1.0',
    description: 'All API methods on the Laser arena Control app.',
    title      : 'Laser arena Control API',
)]
#[OA\Server(url: 'https://lac.local')]
class OpenApi
{
}
