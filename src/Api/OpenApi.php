<?php

namespace App\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version    : '1.0',
    description: 'All API methods on the Laser arena Control app.',
    title      : 'Laser arena Control API',
)]
#[OA\Server(url: 'https://localhost', description: 'Dev')]
#[OA\Server(url: 'https://pisek.laserliga.cz', description: 'Laser arena Písek')]
#[OA\Server(url: 'https://fs.laserliga.cz', description: 'FunSpace ČB')]
#[OA\Server(url: 'https://pardubice.laserliga.cz', description: 'Laser game Pardubice')]
class OpenApi
{
}
