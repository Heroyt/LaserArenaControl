<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use Lsr\Lg\Predictor\Contract\GameResultPredictorInterface;
use Lsr\Lg\Predictor\Contract\ModelEvaluatorInterface;
use Lsr\Lg\Predictor\Contract\PredictorModelRepositoryInterface;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Dto\PredictionResult;
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Exception\PredictionException;

final readonly class GameResultPredictor implements GameResultPredictorInterface
{
    /**
     * @param non-empty-list<ModelEvaluatorInterface> $evaluators
     */
    public function __construct(
        private PredictorModelRepositoryInterface $modelRepository,
        private array $evaluators,
    ) {
    }

    public function predict(PredictionTarget $target, PredictionContext $context): PredictionResult {
        $model = $this->modelRepository->findModel($target, $context);
        if ($model === null) {
            throw new PredictionException('No predictor model found for target: ' . $target->value);
        }

        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($model)) {
                return $evaluator->predict($model, $context);
            }
        }

        throw new PredictionException('No evaluator found for model family: ' . $model->modelFamily->value);
    }
}
