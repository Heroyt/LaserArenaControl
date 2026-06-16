<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

enum ResultGameStatusState: string
{
    case UNKNOWN = 'unknown';
    case LOADED = 'loaded';
    case STARTED = 'started';
    case FINISHED = 'finished';
}
