<?php

/** @noinspection PhpToStringImplementationInspection */

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\App;
use App\GameModels\Game\Game;
use App\GameModels\Game\Lasermaxx\Team;
use App\GameModels\Game\Player;
use Dibi\Exception;
use Lsr\Core\Config;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Exceptions\FileException;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Lg\Results\Exception\ResultsParseException;
use Lsr\Logging\Logger;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nette\DI\MissingServiceException;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{
    private int $gameLoadedTime;
    private int $gameStartedTime;

    public function __construct(
        Config                                     $config,
        private readonly ResultFileImporter        $resultFileImporter,
        private readonly ResultFileImportFinalizer $resultFileImportFinalizer,
    ) {
        $this->gameLoadedTime = (int)($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $this->gameStartedTime = (int)($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T, P>
     * @param G $game
     * @throws ResultsParseException
     * @throws Throwable
     * @throws FileException
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function importGame(Game $game, string $resultsDir): SuccessResponse|ErrorResponse {
        $logger = new Logger(LOG_DIR . 'results/', 'import');
        $resultsDir = trailingSlashIt($resultsDir);

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

        $file = null;
        if (!empty($game->resultsFile)) {
            $resultsFile = $game->resultsFile;
            $candidates = [$resultsFile];
            if (pathinfo($resultsFile, PATHINFO_EXTENSION) === '') {
                $candidates[] = $resultsFile . '.game';
            }
            if (!$this->isAbsolutePath($resultsFile)) {
                $candidates[] = $resultsDir . $resultsFile;
                if (pathinfo($resultsFile, PATHINFO_EXTENSION) === '') {
                    $candidates[] = $resultsDir . $resultsFile . '.game';
                }
            }

            foreach (array_unique($candidates) as $candidate) {
                if (file_exists($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
        }

        if ($file === null && $game instanceof \App\GameModels\Game\Lasermaxx\Game && !empty($game->fileNumber)) {
            $pattern = $resultsDir . str_pad((string)$game->fileNumber, 4, '0', STR_PAD_LEFT) . '*.game';
            $files = glob($pattern);
            if (empty($files)) {
                return new ErrorResponse(
                    'Cannot find game file.',
                    type: ErrorType::NOT_FOUND,
                    values: ['path' => $pattern]
                );
            }
            if (count($files) > 1) {
                return new ErrorResponse(
                    'Found more than one suitable game file.',
                    type: ErrorType::INTERNAL,
                    values: ['path' => $pattern, 'files' => $files]
                );
            }
            $file = $files[0];
        }
        if ($file === null) {
            return new ErrorResponse(
                'Cannot get game file number.',
                type: ErrorType::NOT_FOUND,
                values: ['game' => $game]
            );
        }

        try {
            $logger->info('Importing file: ' . $file);
            try {
                /** @var AbstractResultsParser<G> $parser */
                $parser = App::getService('result.parser.' . $game::SYSTEM);
            } catch (MissingServiceException) {
                return new ErrorResponse('No parser for this game (' . $game::SYSTEM . ')', type: ErrorType::INTERNAL);
            }
            if (!$parser::checkFile($file)) {
                return
                    new ErrorResponse('Game file cannot be parsed: ' . $file, type: ErrorType::INTERNAL);
            }
            $parser->setFile($file);
            $game = $parser->parse();

            $now = time();

            // Check timestamps
            $isStarted = $game->isStarted();
            $isFreshLoaded = isset($game->fileTime)
                && ($now - $game->fileTime->getTimestamp()) <= $this->gameLoadedTime;
            $isRecentlyStarted = $game->start !== null
                && ($now - $game->start->getTimestamp()) <= $this->gameStartedTime;

            if (!$game->isFinished()) {
                $logger->debug('Game is not finished');

                // The game is not finished and does not contain any results
                // It is either:
                // - an old, un-played game
                // - freshly loaded game
                // - started and not finished game
                // An old game should be ignored, the other 2 cases should be logged and an event should be sent.
                // But only the latest game should be considered

                if ($isStarted && $isRecentlyStarted) { // The game is started
                    $logger->debug('Game is started');
                } elseif ($isFreshLoaded) { // The game is loaded
                    $logger->debug('Game is loaded');
                }
                return new ErrorResponse('Game is not finished', type: ErrorType::VALIDATION);
            }

            // Check players
            $null = true;
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
            $this->resultFileImporter->clearImportedGameState($game, $game::SYSTEM, $logger);
            $this->resultFileImportFinalizer->triggerImported(1);
            $this->resultFileImportFinalizer->finalize([$game], $logger);
        } catch (Exception $e) {
            return new ErrorResponse('Error while parsing game file.', type: ErrorType::INTERNAL, exception: $e);
        }
        return new SuccessResponse(values: ['game' => $game]);
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[a-z]:[\/\\\\]/i', $path) === 1;
    }
}
