<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

enum ResultFileImportStatus: string
{
    case SEEN = 'seen';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case IMPORTED = 'imported';
    case LOADED = 'loaded';
    case STARTED = 'started';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
    case STALE = 'stale';
}
