<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

enum ResultFileScanAction: string
{
    case QUEUE = 'queue';
    case SKIP = 'skip';
    case INVALID = 'invalid';
}
