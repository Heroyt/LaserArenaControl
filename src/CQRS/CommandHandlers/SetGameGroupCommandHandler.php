<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\RecalculateSkillsCommand;
use App\CQRS\Commands\SetGameGroupCommand;
use App\CQRS\Commands\SyncGameCommand;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

final readonly class SetGameGroupCommandHandler implements CommandHandlerInterface
{
    public function __construct(
      private CommandBus $commandBus,
    ) {}

    /**
     * @param  SetGameGroupCommand  $command
     * @return array{game: string, group: int|null}|false
     */
    public function handle(CommandInterface $command) : array | false {
        // Refresh game
        $game = $command->game;
        try {
            $game->fetch(true);
        } catch (ModelNotFoundException) {
            return false;
        }

        $game->group = $command->group;

        try {
            if ($game->save()) {
                $this->commandBus->dispatchAsync(new RecalculateSkillsCommand($game));
                $this->commandBus->dispatchAsync(new SyncGameCommand($game));
                $game->clearCache();
                $game->group?->clearCache();
                return ['game' => $game->code, 'group' => $game->group?->id];
            }
        } catch (Throwable) {
        }
        return false;
    }
}
