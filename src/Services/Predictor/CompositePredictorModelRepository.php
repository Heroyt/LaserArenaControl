<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use Lsr\Lg\Predictor\Contract\PredictorModelRepositoryInterface;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Dto\PredictorModel;
use Lsr\Lg\Predictor\Enum\PredictionTarget;

final readonly class CompositePredictorModelRepository implements PredictorModelRepositoryInterface
{
    /**
     * @param non-empty-list<PredictorModelRepositoryInterface> $repositories
     */
    public function __construct(
        private array $repositories,
    ) {
    }

    public function findModel(PredictionTarget $target, PredictionContext $context): ?PredictorModel {
        foreach ($this->repositories as $repository) {
            $model = $repository->findModel($target, $context);
            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }
}
