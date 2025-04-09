<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\Evo5\GameSimulator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SimulateGameCommand extends Command
{

    public function __construct(
      private readonly GameSimulator $gameSimulator,
    ) {
        parent::__construct();
    }

    public static function getDefaultName() : ?string {
        return 'games:simulate';
    }

    public static function getDefaultDescription() : ?string {
        return 'Simulate the game loaded in 0000.game';
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        $this->gameSimulator->simulate();
        return self::SUCCESS;
    }

}