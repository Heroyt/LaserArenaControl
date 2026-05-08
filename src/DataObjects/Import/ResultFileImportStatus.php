<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

enum ResultFileImportStatus: string
{
    case SEEN = 'seen';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case IMPORTED = 'imported';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
    case STALE = 'stale';
}
