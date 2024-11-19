<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Lasermaxx\Game;
use App\GameModels\Game\Player;
use App\Services\ImportService;
use App\Services\LaserLiga\PlayerProvider;
use App\Tools\Interfaces\ResultsParserInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Serializer;

class ImportGameCommand extends Command
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly PlayerProvider $playerProvider,
      private readonly Serializer $serializer,
    ) {
        parent::__construct('games:import');
    }

    public static function getDefaultName(): ?string {
        return 'games:import';
    }

    public static function getDefaultDescription(): ?string {
        return 'Import games from a directory.';
    }

    protected function configure(): void {
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

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $dir = $input->getArgument('directory');
        $gameCode = $input->getArgument('game');
        $limit = (int) $input->getOption('limit');

        if (!file_exists($dir) || !is_dir($dir)) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) . 'Error: argument must be a valid directory.' . Colors::reset()
            );
            return self::FAILURE;
        }

        if (!empty($gameCode)) {
            try {
                $game = GameFactory::getByCode($gameCode);
            } catch (\Throwable $e) {
                $output->writeln(
                  '<error>Error: Game not found - '.$e->getMessage().'.</error>'
                );
                return self::FAILURE;
            }

            if (!isset($game)) {
                $output->writeln(
                  '<error>Error: Game not found.</error>'
                );
                return self::FAILURE;
            }

            $id = $game->id;
            $code = $game->code;

            $game->clearCache();

            if (isset($game->resultsFile) && file_exists($game->resultsFile)) {
                $file = $game->resultsFile;
            } else if ($game instanceof Game && !empty($game->fileNumber)) {
                $files = glob($dir . str_pad((string) $game->fileNumber, 4, '0', STR_PAD_LEFT) . '*.game');
                if (empty($files)) {
                    $output->writeln('<error>Cannot find game file.</error>');
                return self::FAILURE;
                }
                if (count($files) > 1) {
                    $output->writeln('<error>Found more than one suitable game file. '.$dir . $game->fileNumber . '*.game'.'</error>');
                    $output->writeln(implode(', ', $files));
                return self::FAILURE;
                }
                $file = $files[0];
            } else {
                $output->writeln('<error>Cannot get game file number.</error>');
                return self::FAILURE;
            }

            try {
                /** @var class-string<ResultsParserInterface> $class */
                $class = 'App\\Tools\\ResultParsing\\' . ucfirst($game::SYSTEM) . '\\ResultsParser';

                if (!class_exists($class)) {
                    $output->writeln('<error>No parser for this game (' . $game::SYSTEM . ')</error>');
                    return self::FAILURE;
                }
                if (!$class::checkFile($file)) {
                    $output->writeln('<error>Game file cannot be parsed: ' . $file.'</error>');
                    return self::FAILURE;
                }
                $parser = new $class($this->playerProvider);
                $parser->setFile($file);
                $game = $parser->parse();

                // Check players
                $null = true;
                /** @var Player $player */
                foreach ($game->getPlayers() as $player) {
                    if ($player->score !== 0 || $player->shots !== 0) {
                        $null = false;
                        break;
                    }
                }
                if ($null) {
                    $output->writeln('<error>Game is empty</error>');
                    return self::FAILURE;
                }

                $game->id = $id;
                $game->code = $code;

                if (!$game->save()) {
                    $output->writeln('<error>Failed saving game into DB.</error>');
                    return self::FAILURE;
                }
            } catch (\Exception $e) {
                $output->writeln('<error>Error while parsing game file. '.$e->getMessage().'</error>');
                return self::FAILURE;
            }
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln($this->serializer->serialize($game, 'json'));
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            $output->writeln('<info>Game imported.</info>');
            return self::SUCCESS;
        }

        $response = $this->importService->import($dir, $input->getOption('all'), $limit, $output);
        if ($response instanceof ErrorResponse) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) .
                $response->title .
                Colors::reset()
            );
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            if (!empty($response->values)) {
                $output->writeln(
                    Colors::color(ForegroundColors::RED) .
                    json_encode($response->values, JSON_PRETTY_PRINT) .
                    Colors::reset()
                );
            }
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            return self::FAILURE;
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) .
            'Imported: ' . $response->imported . '/' . $response->total . ' in ' . $response->time . 's' .
            Colors::reset()
        );
        return self::SUCCESS;
    }
}
