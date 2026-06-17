<?php

declare(strict_types=1);

namespace App\Helpers;

use OpenApi\Attributes as OA;

/**
 * @property string $value
 */
#[OA\Schema(type: 'string')]
enum Gender: string
{
    case MALE  = 'm';
    case FEMALE = 'f';
    case OTHER = 'o';
}
