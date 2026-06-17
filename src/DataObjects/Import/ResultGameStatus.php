<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use DateTimeInterface;

readonly class ResultGameStatus
{
    public ResultGameStatusState $state;

    public function __construct(
        public string             $system,
        public ?int               $fileNumber = null,
        public ?int               $playerCount = null,
        public ?DateTimeInterface $loadedAt = null,
        public ?DateTimeInterface $playStartedAt = null,
        public ?DateTimeInterface $playEndedAt = null,
        public ?DateTimeInterface $realEndedAt = null,
        public ?DateTimeInterface $importedAt = null,
        ?ResultGameStatusState    $state = null,
    ) {
        $this->state = $state ?? self::deriveState($loadedAt, $playStartedAt, $playEndedAt, $realEndedAt);
    }

    private static function deriveState(
        ?DateTimeInterface $loadedAt,
        ?DateTimeInterface $playStartedAt,
        ?DateTimeInterface $playEndedAt,
        ?DateTimeInterface $realEndedAt,
    ): ResultGameStatusState {
        if ($realEndedAt !== null || $playEndedAt !== null) {
            return ResultGameStatusState::FINISHED;
        }

        if ($playStartedAt !== null) {
            return ResultGameStatusState::STARTED;
        }

        if ($loadedAt !== null) {
            return ResultGameStatusState::LOADED;
        }

        return ResultGameStatusState::UNKNOWN;
    }

    public static function unknown(string $system): self {
        return new self($system, state: ResultGameStatusState::UNKNOWN);
    }
}
