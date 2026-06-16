<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileVersion;
use DateTimeInterface;
use Dibi\Exception;
use Lsr\Db\DB;
use RuntimeException;

readonly class ResultFileImportStateRepository
{
    public const string TABLE = 'result_file_imports';

    /**
     * @throws Exception
     */
    public function findByPath(string $path): ?ResultFileImportState {
        return $this->findByPathHash($this->pathHash($path));
    }

    /**
     * @throws Exception
     */
    public function findByPathHash(string $pathHash): ?ResultFileImportState {
        $row = DB::select(self::TABLE, '*')
            ->where('[path_hash] = %s', $pathHash)
            ->fetch(cache: false);

        return $row === null ? null : ResultFileImportState::fromRow($row);
    }

    private function pathHash(string $path): string {
        $realPath = realpath($path);
        return sha1($realPath === false ? $path : $realPath);
    }

    /**
     * @throws Exception
     */
    public function saveSeen(
        ResultFileVersion      $version,
        string                 $system,
        ResultFileImportStatus $status = ResultFileImportStatus::SEEN,
        ?DateTimeInterface     $queuedAt = null,
    ): ResultFileImportState {
        $data = [
            'path' => $version->path,
            'path_hash' => $version->pathHash,
            'system' => $system,
            'seen_mtime' => $version->mtime,
            'seen_size' => $version->size,
            'seen_hash' => $version->contentHash,
            'seen_version' => $version->version,
            'queued_at' => $queuedAt,
            'processing_version' => null,
            'processing_started_at' => null,
            'status' => $status->value,
            'attempts' => 0,
            'last_error' => null,
        ];

        if ($this->findByPathHash($version->pathHash) === null) {
            $this->insert($data);
        } else {
            $this->updateByPathHash($version->pathHash, $data);
        }

        $saved = $this->findByPathHash($version->pathHash);
        if ($saved === null) {
            throw new RuntimeException('Failed to load saved result file import state.');
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $data
     * @throws Exception
     */
    private function insert(array $data): void {
        $columns = array_keys($data);
        $placeholders = array_map([$this, 'placeholder'], $data);

        DB::query(
            sprintf(
                'INSERT INTO %%n (`%s`) VALUES (%s)',
                implode('`, `', $columns),
                implode(', ', $placeholders),
            ),
            self::TABLE,
            ...array_values($data),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @throws Exception
     */
    private function updateByPathHash(string $pathHash, array $data): void {
        unset($data['path_hash']);

        DB::update(
            self::TABLE,
            $data,
            ['path_hash = %s', $pathHash],
        );
    }

    private function placeholder(mixed $value): string {
        return match (true) {
            is_int($value) => '%i',
            is_float($value) => '%f',
            $value instanceof DateTimeInterface => '%dt',
            default => '%s',
        };
    }

    /**
     * @throws Exception
     */
    public function markProcessing(ResultFileVersion $version, DateTimeInterface $now): bool {
        return DB::update(
            self::TABLE,
            [
                    'status' => ResultFileImportStatus::PROCESSING->value,
                    'processing_version' => $version->version,
                    'processing_started_at' => $now,
                    'attempts%sql' => 'attempts + 1',
                    'last_error' => null,
                ],
            ['path_hash = %s AND seen_version = %s', $version->pathHash, $version->version]
        ) > 0;
    }

    /**
     * @throws Exception
     */
    public function markImported(
        ResultFileVersion $version,
        DateTimeInterface $now,
        ?string           $gameCode = null,
        string            $event = 'imported',
    ): bool {
        return $this->markCompleted(
            $version,
            ResultFileImportStatus::IMPORTED,
            $now,
            gameCode: $gameCode,
            event: $event,
            processedHash: $version->contentHash,
        );
    }

    /**
     * @throws Exception
     */
    private function markCompleted(
        ResultFileVersion      $version,
        ResultFileImportStatus $status,
        DateTimeInterface      $now,
        ?string                $gameCode = null,
        ?string                $event = null,
        ?string                $processedHash = null,
    ): bool {
        return DB::update(
            self::TABLE,
            [
                    'status' => $status->value,
                    'processing_version' => null,
                    'processing_started_at' => null,
                    'processed_version' => $version->version,
                    'processed_hash' => $processedHash,
                    'processed_at' => $now,
                    'last_game_code' => $gameCode,
                    'last_event' => $event,
                    'last_error' => null,
                ],
            ['path_hash = %s AND seen_version = %s', $version->pathHash, $version->version]
        ) > 0;
    }

    /**
     * @throws Exception
     */
    public function markSkipped(ResultFileVersion $version, DateTimeInterface $now, string $event = 'skipped'): bool {
        return $this->markCompleted($version, ResultFileImportStatus::SKIPPED, $now, event: $event);
    }

    /**
     * @throws Exception
     */
    public function markLoaded(ResultFileVersion $version, DateTimeInterface $now): bool {
        return $this->markActive($version, ResultFileImportStatus::LOADED, $now, 'game-loaded');
    }

    /**
     * @throws Exception
     */
    public function markStarted(ResultFileVersion $version, DateTimeInterface $now): bool {
        return $this->markActive($version, ResultFileImportStatus::STARTED, $now, 'game-started');
    }

    /**
     * @throws Exception
     */
    private function markActive(
        ResultFileVersion      $version,
        ResultFileImportStatus $status,
        DateTimeInterface      $now,
        string                 $event,
    ): bool {
        return DB::update(
            self::TABLE,
            [
                    'status' => $status->value,
                    'queued_at' => $now,
                    'processing_version' => null,
                    'processing_started_at' => null,
                    'processed_version' => null,
                    'processed_hash' => null,
                    'processed_at' => null,
                    'last_game_code' => null,
                    'last_event' => $event,
                    'last_error' => null,
                ],
            ['path_hash = %s AND seen_version = %s', $version->pathHash, $version->version]
        ) > 0;
    }

    /**
     * @throws Exception
     */
    public function markStale(ResultFileVersion $version, DateTimeInterface $now): bool {
        return $this->markCompleted($version, ResultFileImportStatus::STALE, $now, event: 'stale');
    }

    /**
     * @throws Exception
     */
    public function markFailed(ResultFileVersion $version, string $error): bool {
        return DB::update(
            self::TABLE,
            [
                    'status' => ResultFileImportStatus::FAILED->value,
                    'processing_version' => null,
                    'processing_started_at' => null,
                    'last_error' => mb_substr($error, 0, 1000),
                ],
            ['path_hash = %s AND seen_version = %s', $version->pathHash, $version->version]
        ) > 0;
    }
}
