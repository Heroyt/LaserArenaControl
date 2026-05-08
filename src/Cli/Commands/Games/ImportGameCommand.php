<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\GameModels\Factory\GameFactory;
use App\Services\ImportService;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Serializer;
use Throwable;

class ImportGameCommand extends Command
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly CommandBus    $commandBus,
        private readonly Serializer    $serializer,
    ) {
        parent::__construct('games:import');
    }

    public static function getDefaultName(): ?string
    {
        return 'games:import';
    }

    public static function getDefaultDescription(): ?string
    {
        return 'Import games from a directory.';
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Import all games in a directory - ignore modification time.'
        );
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Limit games to import.'
        );
        $this->addArgument('directory', InputArgument::REQUIRED, 'Results directory');
        $this->addArgument('game', InputArgument::OPTIONAL, 'Game code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $input->getArgument('directory');
        $gameCode = $input->getArgument('game');
        $limit = (int) $input->getOption('limit');

        if (!file_exists($dir) || !is_dir($dir)) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) . 'Error: argument must be a valid directory.' . Colors::reset()
            );
            return self::FAILURE;
        }

        /** @var non-empty-string $dir */
        $dir = trailingslashit($dir);

        if (!empty($gameCode)) {
            try {
                $game = GameFactory::getByCode($gameCode);
            } catch (Throwable $e) {
                $output->writeln(
                    '<error>Error: Game not found - ' . $e->getMessage() . '.</error>'
                );
                return self::FAILURE;
            }

            if (!isset($game)) {
                $output->writeln(
                    '<error>Error: Game not found.</error>'
                );
                return self::FAILURE;
            }

            $response = $this->importService->importGame($game, $dir);

            if ($response instanceof ErrorResponse) {
                $output->writeln('<error>' . $response->title . '</error>');
                if (!empty($response->values)) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
                    $output->writeln($this->serializer->serialize($response->values, 'json'));
                    $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
                }
                return self::FAILURE;
            }

            $output->writeln('<info>Game imported.</info>');
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln($this->serializer->serialize($response->values, 'json'));
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            return self::SUCCESS;
        }

        try {
            $response = $this->commandBus->dispatch(
                new ScanResultsDirectoryCommand($dir, $input->getOption('all'), $limit)
            );
        } catch (Throwable $e) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) .
                $e->getMessage() .
                Colors::reset()
            );
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln($e->getTraceAsString());
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            return self::FAILURE;
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) .
            'Queued: ' . $response->queued . '/' . $response->seen .
            ' changed result files. Unchanged: ' . $response->unchanged . '. Invalid: ' . $response->invalid . '.' .
            Colors::reset()
        );
        if ($response->errors !== []) {
            $output->writeln('<comment>Scan completed with ' . count($response->errors) . ' non-fatal errors.</comment>');
        }
        return self::SUCCESS;
    }
}
