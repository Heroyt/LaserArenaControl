<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ResultsScanResult', type: 'object')]
readonly class ResultsScanResult implements JsonSerializable
{
    /**
     * @param list<QueuedResultFileImport> $queuedFiles
     * @param list<ImportResultFileCommandResult> $importResults
     * @param list<ResultsScanError> $errors
     * @param list<ResultFileScanDecision> $decisions
     */
    public function __construct(
        #[OA\Property]
        public string $dir,
        #[OA\Property]
        public int    $seen,
        #[OA\Property]
        public int    $queued,
        #[OA\Property]
        public int    $unchanged,
        #[OA\Property]
        public int    $invalid,
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/QueuedResultFileImport'))]
        public array $queuedFiles = [],
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/ImportResultFileCommandResult'))]
        public array $importResults = [],
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/ResultsScanError'))]
        public array  $errors = [],
        #[OA\Property(items: new OA\Items(ref: '#/components/schemas/ResultFileScanDecision'))]
        public array $decisions = [],
    ) {
    }

    /**
     * @param list<ImportResultFileCommandResult> $importResults
     */
    public function withImportResults(array $importResults): self {
        return new self(
            $this->dir,
            $this->seen,
            $this->queued,
            $this->unchanged,
            $this->invalid,
            $this->queuedFiles,
            $importResults,
            $this->errors,
            $this->decisions,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
