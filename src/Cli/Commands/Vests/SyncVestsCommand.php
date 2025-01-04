<?php

declare(strict_types=1);

namespace App\Cli\Commands\Vests;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Services\LaserLiga\LigaApi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncVestsCommand extends Command
{
    public function __construct(
      private readonly LigaApi $api,
      ?string                  $name = null
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName() : ?string {
        return 'vests:sync';
    }

    public static function getDefaultDescription() : ?string {
        return 'Sync vests to laser liga.';
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        if ($this->api->syncVests()) {
            $output->writeln(
              Colors::color(ForegroundColors::GREEN).
              'Synchronized vests'.
              Colors::reset()
            );
            return self::SUCCESS;
        }

        $output->writeln(
          Colors::color(ForegroundColors::RED).
          'Sync failed'.
          Colors::reset()
        );
        return self::FAILURE;
    }
}
