<?php

declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\ImportResultFileCommandHandler;
use App\DataObjects\Import\ImportResultFileCommandResult;
use App\DataObjects\Import\QueuedResultFileImport;
use App\DataObjects\Import\ResultFileVersion;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<ImportResultFileCommandResult>
 */
final readonly class ImportResultFileCommand implements CommandInterface
{
    /**
     * @param array<int|string, int> $preservePlayerIdsByVest
     * @param array<int, int> $preserveTeamIdsByColor
     */
    public function __construct(
        public string  $path,
        public string  $pathHash,
        public string  $system,
        public int     $mtime,
        public int     $size,
        public string  $contentHash,
        public string  $version,
        public ?string $content = null,
        public int     $timeoutSeconds = 30,
        public bool $force = false,
        public ?int $preserveGameId = null,
        public ?string $preserveGameCode = null,
        public array $preservePlayerIdsByVest = [],
        public array $preserveTeamIdsByColor = [],
    ) {
    }

    public static function fromQueuedFile(
        QueuedResultFileImport $queuedFile,
        int                    $timeoutSeconds = 30,
        bool                   $force = false,
    ): self {
        return new self(
            path: $queuedFile->path,
            pathHash: $queuedFile->pathHash,
            system: $queuedFile->system,
            mtime: $queuedFile->mtime,
            size: $queuedFile->size,
            contentHash: $queuedFile->contentHash,
            version: $queuedFile->version,
            content: $queuedFile->content,
            timeoutSeconds: $timeoutSeconds,
            force: $force,
        );
    }

    public function getHandler(): string {
        return ImportResultFileCommandHandler::class;
    }

    public function toVersion(): ResultFileVersion {
        return new ResultFileVersion(
            path: $this->path,
            pathHash: $this->pathHash,
            mtime: $this->mtime,
            size: $this->size,
            contentHash: $this->contentHash,
            version: $this->version,
        );
    }
}
