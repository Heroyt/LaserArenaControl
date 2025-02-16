<?php

/** @noinspection PhpToStringImplementationInspection */

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Api\Response\ImportResponse;
use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\App;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Services\LaserLiga\LigaApi;
use App\Services\LaserLiga\PlayerProvider;
use Dibi\Exception;
use Lsr\Core\Config;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Exceptions\FileException;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Lg\Results\Exception\ResultsParseException;
use Lsr\Lg\Results\Interface\ResultsParserInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nette\DI\MissingServiceException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Metrics\Metrics;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{
    public const int ERROR_STATUS_INFO_SET = 1;

    /** @var array{error?:string,exception?:string,sql?:string}[]|string[] */
    private array $errors = [];
    private int $gameLoadedTime;
    private int $gameStartedTime;

    public function __construct(
      private readonly EventService   $eventService,
      private readonly LockFactory    $lockFactory,
      private readonly LigaApi        $ligaApi,
      Config                          $config,
      private readonly FeatureConfig  $featureConfig,
      private readonly Metrics        $metrics,
      private readonly PlayerProvider $playerProvider,
    ) {
        $this->gameLoadedTime = (int) ($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $this->gameStartedTime = (int) ($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
    }

    /**
     * @throws ResultsParseException
     * @throws Throwable
     * @throws FileException
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function importGame(Game $game, string $resultsDir) : SuccessResponse | ErrorResponse {
        $logger = new Logger(LOG_DIR.'results/', 'import');

        $id = $game->id;
        $code = $game->code;

        $playerIds = [];
        $teamIds = [];

        foreach ($game->players as $player) {
            $playerIds[$player->vest] = $player->id;
        }

        foreach ($game->teams as $team) {
            $teamIds[$team->color] = $team->id;
        }

        $game->clearCache();

        if (isset($game->resultsFile) && file_exists($game->resultsFile)) {
            $file = $game->resultsFile;
        }
        else {
            if ($game instanceof \App\GameModels\Game\Lasermaxx\Game && !empty($game->fileNumber)) {
                $pattern = $resultsDir.str_pad((string) $game->fileNumber, 4, '0', STR_PAD_LEFT).'*.game';
                $files = glob($pattern);
                if (empty($files)) {
                    return new ErrorResponse(
                              'Cannot find game file.',
                      type  : ErrorType::NOT_FOUND,
                      values: ['path' => $pattern]
                    );
                }
                if (count($files) > 1) {
                    return new ErrorResponse(
                              'Found more than one suitable game file.',
                      type  : ErrorType::INTERNAL,
                      values: ['path' => $pattern, 'files' => $files]
                    );
                }
                $file = $files[0];
            }
            else {
                return new ErrorResponse(
                          'Cannot get game file number.',
                  type  : ErrorType::NOT_FOUND,
                  values: ['game' => $game]
                );
            }
        }

        try {
            $logger->info('Importing file: '.$file);
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\'.ucfirst($game::SYSTEM).'\\ResultsParser';
            if (!class_exists($class)) {
                return
                  new ErrorResponse('No parser for this game ('.$game::SYSTEM.')', type: ErrorType::INTERNAL);
            }
            if (!$class::checkFile($file)) {
                return
                  new ErrorResponse('Game file cannot be parsed: '.$file, type: ErrorType::INTERNAL);
            }
            $parser = new $class($this->playerProvider);
            $parser->setFile($file);
            $game = $parser->parse();

            $now = time();

            if (!isset($game->importTime)) {
                $logger->debug('Game is not finished');

                // The game is not finished and does not contain any results
                // It is either:
                // - an old, un-played game
                // - freshly loaded game
                // - started and not finished game
                // An old game should be ignored, the other 2 cases should be logged and an event should be sent.
                // But only the latest game should be considered

                // The game is started
                if (
                  $game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp(
                    )) <= $this->gameStartedTime
                ) {
                    $logger->debug('Game is started');
                }
                // The game is loaded
                if (
                  !$game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp(
                    )) <= $this->gameLoadedTime
                ) {
                    $logger->debug('Game is loaded');
                }
                return new ErrorResponse('Game is not finished', type: ErrorType::VALIDATION);
            }

            // Check players
            $null = true;
            /** @var Player $player */
            foreach ($game->players as $player) {
                if (isset($playerIds[$player->vest])) {
                    $player->id = $playerIds[$player->vest];
                }
                if ($player->score !== 0 || $player->shots !== 0) {
                    $null = false;
                    break;
                }
            }
            foreach ($game->teams as $team) {
                if (isset($teamIds[$team->color])) {
                    $team->id = $teamIds[$team->color];
                }
            }
            if ($null) {
                $logger->warning('Game is empty');
                // Empty game - no shots, no hits, etc..
                return new ErrorResponse('Game is empty', type: ErrorType::VALIDATION);
            }

            $game->id = $id;
            $game->code = $code;

            if (!$game->save()) {
                throw new ResultsParseException('Failed saving game into DB.');
            }
            $game::clearModelCache();
        } catch (Exception $e) {
            return new ErrorResponse('Error while parsing game file.', type: ErrorType::INTERNAL, exception: $e);
        }
        return new SuccessResponse(values: ['game' => $game]);
    }

    /**
     * Unified import method for CLI or API controller
     *
     * Handles result import the same for both, but outputs differently.
     *
     * @param  non-empty-string  $resultsDir  Results directory passed from a Controller
     * @param  bool  $all  If true - ignore file modification time and import all files
     * @param  int  $limit
     * @param  OutputInterface|null  $output
     *
     * @return ImportResponse|ErrorResponse
     * @throws ModelNotFoundException
     * @throws Throwable
     * @throws JobsException
     */
    public function import(
      string           $resultsDir,
      bool             $all = false,
      int              $limit = 0,
      ?OutputInterface $output = null
    ) : ImportResponse | ErrorResponse {
        // Validate results directory
        if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
            return new ErrorResponse(
                      'Results directory does not exist.',
                      ErrorType::VALIDATION,
              values: ['dir' => $resultsDir]
            );
        }

        // Create logger
        try {
            $logger = new Logger(LOG_DIR.'results/', 'import');
        } catch (DirectoryCreationException $e) {
            return new ErrorResponse('Failed to create a logging directory.', ErrorType::INTERNAL, exception: $e);
        }

        $this->metrics->add('import_called', 1, [$resultsDir]);

        // Lock import to allow only 1 import process to run in this directory
        $lock = $this->lockFactory->createLock('results-import-'.md5($resultsDir), ttl: 60);

        $output?->writeln('Waiting for lock');
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
                /** @var list<string>|false $resultFiles */
                $resultFiles = glob($resultsDir.$parser::getFileGlob());
                if ($resultFiles === false) {
                    $resultFiles = [];
                }

                // Import all files
                $importedSystem = 0;
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
                        /** @var Game $game */
                        $game = $parser->parse();
                        if (!isset($game->importTime)) {
                            $logger->debug('Game is not finished');
                            $output?->writeln('Game is not finished');

                            // The game is not finished and does not contain any results
                            // It is either:
                            // - an old, un-played game
                            // - freshly loaded game
                            // - started and not finished game
                            // An old game should be ignored, the other 2 cases should be logged and an event
                            // should be sent.
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
                        foreach ($game->players as $player) {
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
                        $importedSystem++;
                    } catch (Throwable $e) {
                        $logger->error($e->getMessage());
                        $logger->debug($e->getTraceAsString());
                        $output?->writeln(Colors::color(ForegroundColors::RED).$e->getMessage().Colors::reset());
                        $errors[] = $e;
                    }
                }
                $this->metrics->add('games_imported', $importedSystem, [$system]);
            }

            // Update check timestamp
            if ($imported > 0) {
                try {
                    Info::set($resultsDir.'check', $now);
                } catch (Exception $e) {
                    $lock->release();
                    return $this->errorHandle($e, statusCode: $this::ERROR_STATUS_INFO_SET);
                }
            }

            // Set last unfinished game event
            if (isset($lastUnfinishedGame)) {
                $logger->debug(
                  'Setting last unfinished game: "'.$lastUnfinishedGame::SYSTEM.'-'.
                  $lastEvent.'" - '.$lastUnfinishedGame->resultsFile
                );
                $output?->writeln(
                  'Setting last unfinished game: "'.$lastUnfinishedGame::SYSTEM.'-'.
                  $lastEvent.'" - '.$lastUnfinishedGame->resultsFile
                );
                try {
                    Info::set($lastUnfinishedGame::SYSTEM.'-'.$lastEvent, $lastUnfinishedGame);
                    $this->eventService->trigger($lastEvent, ['game' => $lastUnfinishedGame->resultsFile]);
                } catch (Exception $e) {
                    $lock->release();
                    return $this->errorHandle($e, statusCode: self::ERROR_STATUS_INFO_SET);
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
        return new ImportResponse(0, 0, 0, []);
    }

    /**
     * Handle an error message according to current controller
     *
     * @param  string|Throwable|string[]|Throwable[]  $data
     * @param  int  $statusCode
     *
     * @return ErrorResponse
     */
    private function errorHandle(string | Throwable | array $data, int $statusCode = 0) : ErrorResponse {
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
                $this->errors[] = $info;
            }
        }

        return new ErrorResponse(
                  'An error has occured',
                  ErrorType::INTERNAL,
          values: ['code' => $statusCode, 'errors' => $errors],
        );
    }
}
