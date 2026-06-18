<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Exception;
use JsonException;
use Lsr\Db\DB;
use Lsr\Lg\Predictor\Dto\PredictorModel;
use Lsr\Lg\Predictor\Model\ModelArtifactImporter;
use RuntimeException;

final readonly class PredictorModelImportService
{
    public function __construct(
        private ModelArtifactImporter $artifactImporter,
    ) {
    }

    /**
     * @throws Exception
     */
    public function importPath(
        string $path,
        bool $activate = false,
        ?string $createdBy = null,
        ?string $notes = null,
    ): PredictorModelImportResult {
        $path = $this->resolvePath($path);
        $payload = $this->readJsonFile($path);

        if (isset($payload['models']) && is_array($payload['models'])) {
            return $this->importManifest($path, $payload, $activate, $createdBy, $notes);
        }

        $this->importModelFile($path, null, $activate, $createdBy, $notes);

        return new PredictorModelImportResult(imported: 1, updated: 0, skipped: 0, activated: $activate ? 1 : 0);
    }

    /**
     * @param array<string,mixed> $manifest
     * @throws Exception
     */
    private function importManifest(
        string $manifestPath,
        array $manifest,
        bool $activate,
        ?string $createdBy,
        ?string $notes,
    ): PredictorModelImportResult {
        $manifestDir = dirname($manifestPath);
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $activated = 0;

        /** @var list<mixed> $models */
        $models = array_values($manifest['models']);
        foreach ($models as $modelEntry) {
            if ( ! is_array($modelEntry) || ! isset($modelEntry['model_file'])) {
                $skipped++;
                continue;
            }

            $modelPath = $this->resolvePath((string)$modelEntry['model_file'], $manifestDir);
            $hash = isset($modelEntry['payload_hash']) ? (string)$modelEntry['payload_hash'] : null;
            $wasUpdate = $this->importModelFile($modelPath, $hash, $activate, $createdBy, $notes);
            if ($wasUpdate) {
                $updated++;
            } else {
                $imported++;
            }
            if ($activate) {
                $activated++;
            }
        }

        return new PredictorModelImportResult($imported, $updated, $skipped, $activated);
    }

    /**
     * @throws Exception
     */
    private function importModelFile(
        string $path,
        ?string $payloadHash,
        bool $activate,
        ?string $createdBy,
        ?string $notes,
    ): bool {
        $payloadJson = file_get_contents($path);
        if ($payloadJson === false) {
            throw new RuntimeException('Unable to read model file: ' . $path);
        }

        $payload = $this->readJsonString($payloadJson, $path);
        $model = $this->artifactImporter->importArray($payload);
        $payloadHash ??= hash('sha256', $payloadJson);
        $existingId = $this->findExistingId($model->modelId, $payloadHash);
        $data = $this->modelData($model, $payload, $payloadJson, $payloadHash, $activate, $createdBy, $notes);

        if ($existingId === null) {
            DB::insert(DatabasePredictorModelRepository::TABLE, $data);
            return false;
        }

        if ( ! $activate) {
            unset($data['active'], $data['activated_at']);
        }

        DB::update(
            DatabasePredictorModelRepository::TABLE,
            $data,
            ['[id] = %i', $existingId],
        );

        return true;
    }

    /**
     * @throws Exception
     */
    private function findExistingId(string $modelId, string $payloadHash): ?int {
        $row = DB::select(DatabasePredictorModelRepository::TABLE, ['id'])
            ->where('[model_id] = %s OR [payload_hash] = %s', $modelId, $payloadHash)
            ->fetch(cache: false);

        return $row === null ? null : (int)$row['id'];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function modelData(
        PredictorModel $model,
        array $payload,
        string $payloadJson,
        string $payloadHash,
        bool $activate,
        ?string $createdBy,
        ?string $notes,
    ): array {
        $metrics = $this->objectOrNull($payload['metrics'] ?? null);
        $training = $this->objectOrNull($payload['training'] ?? null);
        $trainedAt = $this->date($training['trained_at'] ?? null);

        return [
            'model_id'         => $model->modelId,
            'version'          => $this->version($model->modelId),
            'model_family'     => $model->modelFamily->value,
            'target'           => $model->target->value,
            'system'           => $model->scope->system,
            'arena_id'         => $model->scope->arenaId,
            'game_mode_id'     => $model->scope->gameModeId,
            'mode_group'       => $model->scope->modeGroup,
            'game_type'        => $model->scope->gameType?->value,
            'team_count'       => $model->scope->teamCount,
            'fallback_level'   => $model->scope->fallbackLevel,
            'payload'          => $payloadJson,
            'payload_hash'     => $payloadHash,
            'sample_count'     => (int)($training['rows'] ?? 0),
            'train_rows'       => (int)($training['train_rows'] ?? 0),
            'test_rows'        => (int)($training['test_rows'] ?? 0),
            'mae'              => $this->nullableFloat($metrics['mae'] ?? null),
            'rmse'             => $this->nullableFloat($metrics['rmse'] ?? null),
            'r2'               => $this->nullableFloat($metrics['r2'] ?? null),
            'metrics'          => $metrics === null ? null : $this->encodeJson($metrics),
            'training_filters' => isset($training['filters']) && is_array($training['filters'])
                ? $this->encodeJson(array_values($training['filters']))
                : null,
            'trained_at'       => $trainedAt,
            'imported_at'      => new DateTimeImmutable(),
            'activated_at'     => $activate ? new DateTimeImmutable() : null,
            'created_by'       => $createdBy,
            'active'           => $activate ? 1 : 0,
            'notes'            => $notes,
        ];
    }

    private function resolvePath(string $path, ?string $baseDir = null): string {
        $candidate = $path;
        if ($baseDir !== null && ! str_starts_with($path, '/')) {
            $candidate = rtrim($baseDir, '/') . '/' . $path;
        }

        $realPath = realpath($candidate);
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException('Model import file was not found: ' . $candidate);
        }

        return $realPath;
    }

    /**
     * @return array<string,mixed>
     */
    private function readJsonFile(string $path): array {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read JSON file: ' . $path);
        }

        return $this->readJsonString($json, $path);
    }

    /**
     * @return array<string,mixed>
     */
    private function readJsonString(string $json, string $path): array {
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON file: ' . $path, previous: $e);
        }

        if ( ! is_array($payload)) {
            throw new RuntimeException('JSON root must be an object: ' . $path);
        }

        /** @var array<string,mixed> $payload */
        return $payload;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function objectOrNull(mixed $value): ?array {
        if ( ! is_array($value)) {
            return null;
        }

        /** @var array<string,mixed> $value */
        return $value;
    }

    private function version(string $modelId): string {
        $parts = explode('.', $modelId);
        $version = end($parts);

        return $version === '' ? 'unknown' : substr($version, 0, 64);
    }

    private function date(mixed $value): ?DateTimeInterface {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if ( ! is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    private function nullableFloat(mixed $value): ?float {
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * @param mixed $payload
     */
    private function encodeJson(mixed $payload): string {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode predictor model metadata.', previous: $e);
        }
    }
}
