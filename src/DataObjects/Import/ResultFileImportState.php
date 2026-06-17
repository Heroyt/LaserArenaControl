<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use DateTimeImmutable;
use DateTimeInterface;

readonly class ResultFileImportState
{
    public function __construct(
        public int                    $id,
        public string                 $path,
        public string                 $pathHash,
        public string                 $system,
        public int                    $seenMtime,
        public int                    $seenSize,
        public string                 $seenHash,
        public string                 $seenVersion,
        public ResultFileImportStatus $status,
        public int                    $attempts,
        public ?DateTimeInterface     $queuedAt = null,
        public ?string                $processingVersion = null,
        public ?DateTimeInterface     $processingStartedAt = null,
        public ?string                $processedVersion = null,
        public ?string                $processedHash = null,
        public ?DateTimeInterface     $processedAt = null,
        public ?string                $lastError = null,
        public ?string                $lastGameCode = null,
        public ?string                $lastEvent = null,
        public ?DateTimeInterface     $createdAt = null,
        public ?DateTimeInterface     $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed>|\Dibi\Row $row
     */
    public static function fromRow(array|\Dibi\Row $row): self {
        return new self(
            id: (int)self::value($row, 'id_result_file_import'),
            path: (string)self::value($row, 'path'),
            pathHash: (string)self::value($row, 'path_hash'),
            system: (string)self::value($row, 'system'),
            seenMtime: (int)self::value($row, 'seen_mtime'),
            seenSize: (int)self::value($row, 'seen_size'),
            seenHash: (string)self::value($row, 'seen_hash'),
            seenVersion: (string)self::value($row, 'seen_version'),
            status: ResultFileImportStatus::from((string)self::value($row, 'status')),
            attempts: (int)self::value($row, 'attempts'),
            queuedAt: self::date(self::value($row, 'queued_at')),
            processingVersion: self::nullableString(self::value($row, 'processing_version')),
            processingStartedAt: self::date(self::value($row, 'processing_started_at')),
            processedVersion: self::nullableString(self::value($row, 'processed_version')),
            processedHash: self::nullableString(self::value($row, 'processed_hash')),
            processedAt: self::date(self::value($row, 'processed_at')),
            lastError: self::nullableString(self::value($row, 'last_error')),
            lastGameCode: self::nullableString(self::value($row, 'last_game_code')),
            lastEvent: self::nullableString(self::value($row, 'last_event')),
            createdAt: self::date(self::value($row, 'created_at')),
            updatedAt: self::date(self::value($row, 'updated_at')),
        );
    }

    /**
     * @param array<string, mixed>|\Dibi\Row $row
     */
    private static function value(array|\Dibi\Row $row, string $key): mixed {
        return $row[$key] ?? null;
    }

    private static function date(mixed $value): ?DateTimeInterface {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }
        return null;
    }

    private static function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = (string)$value;
        return $value === '' ? null : $value;
    }
}
