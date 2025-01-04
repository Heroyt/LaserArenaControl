<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Services\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncGameCommand extends Command
{
    public static function getDefaultName() : ?string {
        return 'games:sync';
    }

    public static function getDefaultDescription() : ?string {
        return 'Sync games to laser liga.';
    }

    protected function configure() : void {
        $this->addArgument('limit', InputArgument::OPTIONAL, 'Games limit', 5);
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Sync timeout');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        $limit = (int) $input->getArgument('limit');
        $timeout = $input->getOption('timeout');

        if (isset($timeout)) {
            $timeout = (float) $timeout;
        }

        $synced = SyncService::syncGames($limit, $timeout);

        $output->writeln(
          Colors::color(ForegroundColors::GREEN).
          sprintf('Synchronized %d games', $synced).
          Colors::reset()
        );
        return self::SUCCESS;
    }
}
