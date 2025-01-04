<?php

declare(strict_types=1);

namespace App\Cli\Commands\LaserLiga;

use App\Services\LaserLiga\PlayerSynchronizationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncPlayersCommand extends Command
{
    public function __construct(
      private readonly PlayerSynchronizationService $synchronizationService,
      ?string                                       $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName() : string {
        return 'laserliga:sync-players';
    }

    public static function getDefaultDescription() : string {
        return 'Synchronize all players from LaserLiga API to local DB.';
    }

    public function run(InputInterface $input, OutputInterface $output) : int {
        $output->writeln('<info>Synchronizing players...</info>');
        $this->synchronizationService->syncAllLocalPlayers();
        $output->writeln('<info>Checked old players for changes...</info>');
        $this->synchronizationService->syncAllLocalPlayers();
        $output->writeln('<info>Loaded all players for this arena.</info>');
        return self::SUCCESS;
    }
}
