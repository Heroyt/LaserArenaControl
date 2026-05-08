<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

readonly class ImportResultFileCommandResult
{
    public function __construct(
        public string                 $path,
        public string                 $version,
        public ResultFileImportStatus $status,
        public ?string                $gameCode = null,
        public ?string                $event = null,
        public ?string                $error = null,
    )
    {
    }
}
