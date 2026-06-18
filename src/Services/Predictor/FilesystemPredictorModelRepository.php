<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use JsonException;
use Lsr\Lg\Predictor\Contract\PredictorModelRepositoryInterface;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Dto\PredictorModel;
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Model\ModelArtifactImporter;

final class FilesystemPredictorModelRepository implements PredictorModelRepositoryInterface
{
    /** @var list<PredictorModel>|null */
    private ?array $models = null;

    public function __construct(
        private readonly string $modelsDirectory,
        private readonly ModelArtifactImporter $importer,
    ) {
    }

    public function findModel(PredictionTarget $target, PredictionContext $context): ?PredictorModel {
        $candidates = array_values(
            array_filter(
                $this->getModels(),
                fn (PredictorModel $model): bool => $model->target === $target && $this->scopeMatches($model, $context),
            ),
        );

        usort(
            $candidates,
            static fn (PredictorModel $a, PredictorModel $b): int => $a->scope->fallbackLevel <=> $b->scope->fallbackLevel,
        );

        return $candidates[0] ?? null;
    }

    /**
     * @return list<PredictorModel>
     */
    private function getModels(): array {
        if ($this->models !== null) {
            return $this->models;
        }

        if ( ! is_dir($this->modelsDirectory)) {
            return $this->models = [];
        }

        $files = glob(rtrim($this->modelsDirectory, '/') . '/*.json');
        if ($files === false) {
            return $this->models = [];
        }

        sort($files);

        $models = [];
        foreach ($files as $file) {
            $models[] = $this->importFile($file);
        }

        return $this->models = $models;
    }

    private function importFile(string $file): PredictorModel {
        try {
            $json = file_get_contents($file);
            if ($json === false) {
                throw new JsonException('Unable to read file.');
            }

            /** @var array<string,mixed> $payload */
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            return $this->importer->importArray($payload);
        } catch (JsonException $e) {
            throw new PredictorModelLoadException('Unable to load predictor model file: ' . $file, previous: $e);
        }
    }

    private function scopeMatches(PredictorModel $model, PredictionContext $context): bool {
        $scope = $model->scope;

        return ($scope->system === null || $scope->system === $context->system)
            && ($scope->arenaId === null || $scope->arenaId === $context->arenaId)
            && ($scope->gameModeId === null || $scope->gameModeId === $context->gameModeId)
            && ($scope->modeGroup === null || $scope->modeGroup === $context->modeGroup)
            && ($scope->gameType === null || $scope->gameType === $context->gameType)
            && ($scope->teamCount === null || $scope->teamCount === $context->teamCount);
    }
}
