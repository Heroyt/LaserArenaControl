<?php

declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\RecalculateScoresCommandHandler;
use App\GameModels\Game\Game;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<bool>
 */
final readonly class RecalculateScoresCommand implements CommandInterface
{
    /**
     * @template G of Game
     * @param  G  $game
     */
    public function __construct(
      public Game $game,
    ) {}

    /**
     * @inheritDoc
     */
    public function getHandler() : string {
        return RecalculateScoresCommandHandler::class;
    }
}
