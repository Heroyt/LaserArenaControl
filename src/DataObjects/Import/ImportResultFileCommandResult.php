<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ImportResultFileCommandResult', type: 'object')]
readonly class ImportResultFileCommandResult implements JsonSerializable
{
    public function __construct(
        #[OA\Property]
        public string                 $path,
        #[OA\Property]
        public string                 $version,
        #[OA\Property(type: 'string')]
        public ResultFileImportStatus $status,
        #[OA\Property(nullable: true)]
        public ?string                $gameCode = null,
        #[OA\Property(nullable: true)]
        public ?string                $event = null,
        #[OA\Property(nullable: true)]
        public ?string                $error = null,
    )
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'version' => $this->version,
            'status' => $this->status->value,
            'gameCode' => $this->gameCode,
            'event' => $this->event,
            'error' => $this->error,
        ];
    }
}
