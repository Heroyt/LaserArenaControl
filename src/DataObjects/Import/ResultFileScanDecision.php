<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use DateTimeInterface;
use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ResultFileScanDecision', type: 'object')]
readonly class ResultFileScanDecision implements JsonSerializable
{
    public function __construct(
        #[OA\Property]
        public string $path,
        #[OA\Property(nullable: true)]
        public ?string $pathHash,
        #[OA\Property(type: 'string')]
        public ResultFileScanAction $action,
        #[OA\Property]
        public string $reason,
        #[OA\Property(nullable: true)]
        public ?ResultFileImportStatus $status = null,
        #[OA\Property(nullable: true)]
        public ?string $seenVersion = null,
        #[OA\Property(nullable: true)]
        public ?string $processedVersion = null,
        #[OA\Property(nullable: true)]
        public ?string $seenHash = null,
        #[OA\Property(nullable: true)]
        public ?string $processedHash = null,
        #[OA\Property(nullable: true)]
        public ?DateTimeInterface $queuedAt = null,
        #[OA\Property(nullable: true)]
        public ?DateTimeInterface $processingStartedAt = null,
        #[OA\Property(nullable: true)]
        public ?DateTimeInterface $processedAt = null,
        #[OA\Property(nullable: true)]
        public ?string $lastEvent = null,
        #[OA\Property(nullable: true)]
        public ?string $lastError = null,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'pathHash' => $this->pathHash,
            'action' => $this->action->value,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'seenVersion' => $this->seenVersion,
            'processedVersion' => $this->processedVersion,
            'seenHash' => $this->seenHash,
            'processedHash' => $this->processedHash,
            'queuedAt' => $this->queuedAt?->format(DATE_ATOM),
            'processingStartedAt' => $this->processingStartedAt?->format(DATE_ATOM),
            'processedAt' => $this->processedAt?->format(DATE_ATOM),
            'lastEvent' => $this->lastEvent,
            'lastError' => $this->lastError,
        ];
    }
}
