<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\SyncGameCommand;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Throwable;

class SyncGameCommandHandler implements CommandHandlerInterface
{
    /**
     * @param  SyncGameCommand  $command
     */
    public function handle(CommandInterface $command) : bool {
        try {
            $command->game->fetch(true);

            // If the sync fails, this will make sure that the game is synced again by cron job.
            $command->game->sync = false;
            $command->game->save();

            return $command->game->sync();
        } catch (Throwable) {
            return false;
        }
    }
}
