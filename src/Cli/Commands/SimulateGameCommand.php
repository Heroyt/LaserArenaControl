<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Core\App;
use App\Services\Evo5\GameSimulator;
use App\Services\Evo6\GameSimulator as Evo6GameSimulator;
use App\Services\GameSimulationState;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SimulateGameCommand extends Command
{
    public function __construct(
        private readonly GameSimulator $gameSimulator,
        private ?Evo6GameSimulator $evo6GameSimulator = null,
    ) {
        parent::__construct();
    }

    public static function getDefaultName(): ?string
    {
        return 'games:simulate';
    }

    public static function getDefaultDescription(): ?string
    {
        return 'Simulate the game loaded in 0000.game';
    }

    protected function configure(): void
    {
        $this->addOption(
            'system',
            's',
            InputOption::VALUE_REQUIRED,
            'LaserMaxx system to simulate (evo5 or evo6)',
            'evo5'
        );
        $this->addOption(
            'state',
            null,
            InputOption::VALUE_REQUIRED,
            'Simulated game state: finished, loaded, or started',
            GameSimulationState::FINISHED->value
        );
        $this->addOption(
            'loaded',
            null,
            InputOption::VALUE_NONE,
            'Shortcut for --state=loaded'
        );
        $this->addOption(
            'started',
            null,
            InputOption::VALUE_NONE,
            'Shortcut for --state=started'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $system = (string)$input->getOption('system');
            $state = $this->getState($input);
            $output->writeln('<info>Starting ' . $system . ' ' . $state->value . ' game simulation...</info>');
            match ($system) {
                'evo5' => $this->gameSimulator->simulate($state),
                'evo6' => $this->getEvo6GameSimulator()->simulate($state),
                default => throw new Exception('Unsupported system "' . $system . '". Use evo5 or evo6.'),
            };
            $output->writeln('<info>Simulation completed successfully.</info>');
            return self::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('<error>Simulation failed: ' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }
    }

    private function getEvo6GameSimulator(): Evo6GameSimulator
    {
        $this->evo6GameSimulator ??= App::getServiceByType(Evo6GameSimulator::class);
        return $this->evo6GameSimulator;
    }

    private function getState(InputInterface $input): GameSimulationState
    {
        if ((bool)$input->getOption('loaded') && (bool)$input->getOption('started')) {
            throw new Exception('Use only one of --loaded or --started.');
        }

        if ((bool)$input->getOption('loaded')) {
            return GameSimulationState::LOADED;
        }

        if ((bool)$input->getOption('started')) {
            return GameSimulationState::STARTED;
        }

        $state = GameSimulationState::tryFrom((string)$input->getOption('state'));
        if ($state === null) {
            throw new Exception('Unsupported state. Use finished, loaded, or started.');
        }

        return $state;
    }
}
