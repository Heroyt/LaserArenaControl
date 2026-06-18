<?php

declare(strict_types=1);

namespace App\Services\Predictor;

final readonly class PredictorModelImportResult
{
    public function __construct(
        public int $imported,
        public int $updated,
        public int $skipped,
        public int $activated,
    ) {
    }
}
