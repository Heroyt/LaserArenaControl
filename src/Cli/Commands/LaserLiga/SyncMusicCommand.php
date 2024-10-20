<?php

declare(strict_types=1);

namespace App\Cli\Commands\LaserLiga;

use App\Services\LaserLiga\LigaApi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMusicCommand extends Command
{
    public function __construct(
        private readonly LigaApi $api,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): string {
        return 'laserliga:sync-music';
    }

    public static function getDefaultDescription(): string {
        return 'Synchronize all music modes to local Liga API.';
    }

    public function run(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Synchronizing music modes...</info>');
        if ($this->api->syncMusicModes()) {
            $output->writeln('<info>Success</info>');
            return self::SUCCESS;
        }
        $output->writeln('<error>Something went wrong, check the logs.</error>');
        return self::FAILURE;
    }
}
