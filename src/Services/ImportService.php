<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Api\Response\ImportResponse;
use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\App;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Tools\AbstractResultsParser;
use App\Tools\ResultParsing\ResultsParser;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Nette\DI\MissingServiceException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{

    /** @var array{error?:string,exception?:string,sql?:string}[]|string[] */
    private array $errors = [];
    private int $gameLoadedTime;
    private int $gameStartedTime;

    public function __construct(
      private readonly EventService   $eventService,
      private readonly ResultsParser  $parser,
      private readonly PlayerProvider $playerProvider,
      private readonly LockFactory    $lockFactory,
      private readonly LigaApi        $ligaApi,
      Config                          $config,
      private readonly FeatureConfig  $featureConfig,
    ) {
        $this->gameLoadedTime = (int) ($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $this->gameStartedTime = (int) ($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
    }

    /**
     * Unified import method for CLI or API controller
     *
     * Handles result import the same for both, but outputs differently.
     *
     * @param  string  $resultsDir  Results directory passed from a Controller
     * @param  bool  $all  If true - ignore file modification time and import all files
     * @param  OutputInterface|null  $output
     *
     * @return ImportResponse|ErrorDto
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function import(
      string           $resultsDir,
      bool             $all = false,
      int              $limit = 0,
      ?OutputInterface $output = null
    ) : ImportResponse | ErrorDto {
        // Validate results directory
        if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
            return new ErrorDto(
                      'Results directory does not exist.',
                      ErrorType::VALIDATION,
              values: ['dir' => $resultsDir]
            );
        }

        // Create logger
        try {
            $logger = new Logger(LOG_DIR.'results/', 'import');
        } catch (DirectoryCreationException $e) {
            return new ErrorDto('Failed to create a logging directory.', ErrorType::INTERNAL, exception: $e);
        }

        // Lock import to allow only 1 import process to run in this directory
        $lock = $this->lockFactory->createLock('results-import-'.md5($resultsDir), ttl: 60);

        if ($lock->acquire(true)) {
            $now = time();

            $errors = [];
            $processedFiles = [];

            // Counters
            $imported = 0;
            $total = 0;
            $start = microtime(true);
            /** @var Game|null $lastUnfinishedGame */
            $lastUnfinishedGame = null;
            $lastEvent = '';

            /** @var Game[] $finishedGames */
            $finishedGames = [];

            // Ensure that the resultsDir has a trailing slash
            $resultsDir = trailingSlashIt($resultsDir);

            /** @var int $lastCheck Timestamp when this directory was last checked */
            $lastCheck = Info::get($resultsDir.'check', 0);

            foreach (GameFactory::getSupportedSystems() as $system) {
                if ($limit > 0 && $total >= $limit) {
                    break;
                }

                // Find a parser for this system
                try {
                    /** @var AbstractResultsParser $parser */
                    $parser = App::getService('result.parser.'.$system);
                } catch (MissingServiceException $e) {
                    $output?->writeln('Cannot find parser for system result.parser.'.$system);
                    $logger->exception($e);
                    continue;
                }

                // Find all files
                /** @var string[] $resultFiles */
                $resultFiles = glob($resultsDir.$parser::getFileGlob());

                // Import all files
                foreach ($resultFiles as $file) {
                    if ($limit > 0 && $total >= $limit) {
                        break;
                    }

                    // Skip duplicate and invalid files
                    if (
                      isset($processedFiles[$file]) ||
                      str_ends_with($file, '0000.game') ||
                      !$parser::checkFile($file)
                    ) {
                        // Invalid or duplicate file
                        continue;
                    }

                    $processedFiles[$file] = true;

                    // Skip unmodified files
                    if (!$all && filemtime($file) < $lastCheck) {
                        continue;
                    }

                    $total++;
                    $logger->info('Importing file: '.$file);
                    $output?->writeln('Importing file: '.$file);

                    try {
                        $parser->setFile($file);
                        //foreach ($parser->getFileLines() as $line) {
                        //    $output?->writeln(json_encode($line));
                        //}
                        $game = $parser->parse();
                        //$output?->writeln(json_encode($game, JSON_PRETTY_PRINT));
                        if (!isset($game->importTime)) {
                            $logger->debug('Game is not finished');
                            $output?->writeln('Game is not finished');
                            //$output?->writeln(json_encode($game, JSON_PRETTY_PRINT));

                            // The game is not finished and does not contain any results
                            // It is either:
                            // - an old, un-played game
                            // - freshly loaded game
                            // - started and not finished game
                            // An old game should be ignored, the other 2 cases should be logged and an event should be sent.
                            // But only the latest game should be considered

                            // TODO: Detect manually stopped game and delete game-started

                            // The game is started
                            if (
                              $game->started &&
                              isset($game->fileTime) &&
                              ($now - $game->fileTime->getTimestamp()) <= $this->gameStartedTime
                            ) {
                                $lastUnfinishedGame = $game;
                                $lastEvent = 'game-started';
                                $logger->debug('Game is started');
                                $output?->writeln('Game is started');
                                continue;
                            }
                            // The game is loaded
                            if (
                              !$game->started &&
                              isset($game->fileTime) &&
                              ($now - $game->fileTime->getTimestamp()) <= $this->gameLoadedTime
                            ) {
                                // Check if the last unfinished game is not created later
                                if (isset($lastUnfinishedGame) && $game->fileTime < $lastUnfinishedGame->fileTime) {
                                    continue;
                                }
                                $lastUnfinishedGame = $game;
                                $lastEvent = 'game-loaded';
                                $logger->debug('Game is loaded');
                                $output?->writeln('Game is loaded');
                            }
                            continue;
                        }

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
                            $logger->warning('Game is empty');
                            $output?->writeln('Game is empty');
                            continue; // Empty game - no shots, no hits, etc..
                        }

                        if (!$game->save()) {
                            $logger->error('Failed saving game into DB. '.$file);
                            $output?->writeln(
                              Colors::color(ForegroundColors::RED).
                              'Failed saving game into DB'.
                              Colors::reset()
                            );
                            continue;
                        }

                        // Refresh the started-game info to stop the game timer
                        /** @var Game|null $startedGame */
                        $startedGame = Info::get($system.'-game-started');
                        if (isset($startedGame) && $game->resultsFile === $startedGame->resultsFile) {
                            try {
                                Info::set($system.'-game-started', null);
                            } catch (Exception) {
                            }
                        }

                        $gameModel = GameFactory::getById($game->id ?? 0, ['system' => $system]);
                        if (isset($gameModel)) {
                            $finishedGames[] = $gameModel;
                        }
                        $imported++;
                    } catch (Throwable $e) {
                        $logger->error($e->getMessage());
                        $logger->debug($e->getTraceAsString());
                        $output?->writeln(Colors::color(ForegroundColors::RED).$e->getMessage().Colors::reset());
                        $errors[] = $e;
                    }
                }
            }

            // Update check timestamp
            if ($imported > 0) {
                try {
                    Info::set($resultsDir.'check', $now);
                } catch (Exception $e) {
                    $lock->release();
                    return $this->errorHandle($e);
                }
            }

            // Set last unfinished game event
            if (isset($lastUnfinishedGame)) {
                $logger->debug(
                  'Setting last unfinished game: "'.$lastUnfinishedGame::SYSTEM.'-'.$lastEvent.'" - '.$lastUnfinishedGame->resultsFile
                );
                $output?->writeln(
                  'Setting last unfinished game: "'.$lastUnfinishedGame::SYSTEM.'-'.$lastEvent.'" - '.$lastUnfinishedGame->resultsFile
                );
                try {
                    Info::set($lastUnfinishedGame::SYSTEM.'-'.$lastEvent, $lastUnfinishedGame);
                    $this->eventService->trigger($lastEvent, ['game' => $lastUnfinishedGame->resultsFile]);
                } catch (Exception $e) {
                    $lock->release();
                    return $this->errorHandle($e);
                }
            }

            // Send event on new import
            if ($imported > 0) {
                $this->eventService->trigger('game-imported', ['count' => $imported]);
            }

            // Try to synchronize finished games to public
            if (!empty($finishedGames)) {
                $system = $finishedGames[0]::SYSTEM;
                if ($this->featureConfig->isFeatureEnabled('liga')) {
                    if ($this->ligaApi->syncGames($system, $finishedGames)) {
                        $logger->info('Synchronized games to public.');
                        // Set the sync flag
                        foreach ($finishedGames as $finishedGame) {
                            $finishedGame->sync = true;
                            try {
                                $finishedGame->save();
                            } catch (ValidationException $e) {
                                $output?->writeln(
                                  Colors::color(ForegroundColors::RED).
                                  'Failed to synchronize games to public.'.$e->getMessage().
                                  Colors::reset()
                                );
                                $logger->warning('Failed to synchronize games to public');
                                $logger->exception($e);
                            }
                        }
                    }
                    else {
                        $logger->warning('Failed to synchronize games to public');
                        $output?->writeln(
                          Colors::color(ForegroundColors::RED).
                          'Failed to synchronize games to public'.
                          Colors::reset()
                        );
                    }
                }

                /** @var ResultsPrecacheService $precacheService */
                $precacheService = App::getService('resultPrecache');
                $precacheService->prepareGamePrecache(
                  ...array_map(static fn(Game $game) => $game->code, $finishedGames)
                );
            }
            else {
                $logger->info('No games to synchronize to public');
            }

            $lock->release();
            if (!empty($errors)) {
                return $this->errorHandle($errors);
            }

            return new ImportResponse(
              $imported,
              $total,
              round(microtime(true) - $start, 2),
              $this->errors
            );
        }
    }

    /**
     * Handle an error message according to current controller
     *
     * @param  string|Throwable|string[]|Throwable[]  $data
     * @param  int  $statusCode
     *
     * @return void
     * @throws JsonException
     */
    private function errorHandle(string | Throwable | array $data, int $statusCode = 0) : ErrorDto {
        if (!is_array($data)) {
            $data = [$data];
        }

        $errors = [];
        foreach ($data as $error) {
            $info = [];
            if (is_string($error)) {
                $info['error'] = $error;
            }
            else {
                if ($error instanceof \Exception) {
                    $info = [
                      'error'     => 'An exception has occurred.',
                      'exception' => $error->getMessage(),
                    ];
                    if ($error instanceof Exception) {
                        $info['sql'] = $error->getSql();
                    }
                }
            }
            $errors[] = $info;
            if ($statusCode === 0) {
                // @phpstan-ignore-next-line
                $this->errors[] = $info;
            }
        }

        return new ErrorDto(
                  'An error has occured',
                  ErrorType::INTERNAL,
          values: ['code' => $statusCode, 'errors' => $errors],
        );
    }
}