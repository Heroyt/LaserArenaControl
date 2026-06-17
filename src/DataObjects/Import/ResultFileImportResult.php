<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

use App\GameModels\Game\Game;

readonly class ResultFileImportResult
{
    /**
     * @template G of Game
     * @param G|null $game
     * @param G|null $unfinishedGame
     */
    public function __construct(
        public bool   $imported = false,
        public ?Game  $game = null,
        public ?Game  $unfinishedGame = null,
        public string $unfinishedEvent = '',
        public bool   $empty = false,
        public bool   $saveFailed = false,
    ) {
    }

    /**
     * @template SourceGame of Game
     * @param SourceGame $game
     */
    public static function skipped(Game $game): self {
        return new self(game: $game);
    }

    /**
     * @template SourceGame of Game
     * @param SourceGame $game
     */
    public static function unfinished(Game $game, string $event): self {
        return new self(unfinishedGame: $game, unfinishedEvent: $event);
    }

    /**
     * @template SourceGame of Game
     * @param SourceGame $game
     */
    public static function imported(Game $game): self {
        return new self(imported: true, game: $game);
    }

    /**
     * @template SourceGame of Game
     * @param SourceGame $game
     */
    public static function empty(Game $game): self {
        return new self(game: $game, empty: true);
    }

    /**
     * @template SourceGame of Game
     * @param SourceGame $game
     */
    public static function saveFailed(Game $game): self {
        return new self(game: $game, saveFailed: true);
    }
}
