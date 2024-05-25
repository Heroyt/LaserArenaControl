<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Lasermaxx\Game;
use App\GameModels\Game\Player;
use App\Services\ImportService;
use App\Services\PlayerProvider;
use App\Services\TaskProducer;
use App\Tasks\GameImportTask;
use App\Tasks\Payloads\GameImportPayload;
use App\Tools\Interfaces\ResultsParserInterface;
use Exception;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\ArchiveCreationException;
use Lsr\Logging\Logger;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use ZipArchive;

class Results extends ApiController
{
    private int $gameLoadedTime;
    private int $gameStartedTime;

    public function __construct(
      private readonly PlayerProvider $playerProvider,
      private readonly ImportService  $importService,
      Config                          $config,
      private readonly TaskProducer   $taskProducer
    ) {
        parent::__construct();
        $this->gameLoadedTime = (int) ($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $this->gameStartedTime = (int) ($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws Throwable
     * @throws ValidationException
     */
    #[OA\Post('/api/results/import')]
    public function import(Request $request) : ResponseInterface {
        $resultsDir = $request->getPost('dir', '');
        if (empty($resultsDir)) {
            return $this->respond(
              new ErrorDto(
                      'Missing required argument "dir". Valid results directory is expected.',
                type: ErrorType::VALIDATION
              ),
              400
            );
        }

        $this->taskProducer->push(GameImportTask::class, new GameImportPayload($resultsDir));
        return $this->respond('');
    }

    /**
     * Import one game (again)
     *
     * @param  Request  $request
     * @param  string  $game
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws Throwable
     */
    #[OA\Post('/api/results/import/{game}')]
    public function importGame(Request $request, string $game = '') : ResponseInterface {
        $logger = new Logger(LOG_DIR.'results/', 'import');
        $resultsDir = trailingSlashIt($request->getPost('dir', DEFAULT_RESULTS_DIR));

        try {
            $gameObj = GameFactory::getByCode($game);
        } catch (Throwable $e) {
            return $this->respond(
              new ErrorDto(
                           'Error while getting the game by code.',
                type     : ErrorType::INTERNAL,
                exception: $e
              ),
              500
            );
        }
        if (!isset($gameObj)) {
            return $this->respond(
              new ErrorDto('Unknown game.', type: ErrorType::NOT_FOUND),
              404
            );
        }

        $gameObj->clearCache();

        if (isset($gameObj->resultsFile) && file_exists($gameObj->resultsFile)) {
            $file = $gameObj->resultsFile;
        }
        else {
            if ($gameObj instanceof Game && !empty($gameObj->fileNumber)) {
                $files = glob($resultsDir.str_pad($gameObj->fileNumber, 4, '0', STR_PAD_LEFT).'*.game');
                if (empty($files)) {
                    return $this->respond(
                      new ErrorDto(
                                'Cannot find game file.',
                        type  : ErrorType::NOT_FOUND,
                        values: ['path' => $resultsDir.$gameObj->fileNumber.'*.game']
                      ),
                      404
                    );
                }
                if (count($files) > 1) {
                    return $this->respond(
                      new ErrorDto(
                                'Found more than one suitable game file.',
                        type  : ErrorType::INTERNAL,
                        values: ['path' => $resultsDir.$gameObj->fileNumber.'*.game']
                      ),
                      500
                    );
                }
                $file = $files[0];
            }
            else {
                return $this->respond(
                  new ErrorDto(
                            'Cannot get game file number.',
                    type  : ErrorType::NOT_FOUND,
                    values: ['game' => $gameObj]
                  ),
                  417,
                );
            }
        }

        try {
            $logger->info('Importing file: '.$file);
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\'.ucfirst($gameObj::SYSTEM).'\\ResultsParser';
            if (!class_exists($class)) {
                return $this->respond(
                  new ErrorDto('No parser for this game ('.$gameObj::SYSTEM.')', type: ErrorType::INTERNAL),
                  500,
                );
            }
            if (!$class::checkFile($file)) {
                return $this->respond(
                  new ErrorDto('Game file cannot be parsed: '.$file, type: ErrorType::INTERNAL),
                  500,
                );
            }
            $parser = new $class($this->playerProvider);
            $parser->setFile($file);
            $gameObj = $parser->parse();

            $now = time();

            if (!isset($gameObj->importTime)) {
                $logger->debug('Game is not finished');

                // The game is not finished and does not contain any results
                // It is either:
                // - an old, un-played game
                // - freshly loaded game
                // - started and not finished game
                // An old game should be ignored, the other 2 cases should be logged and an event should be sent.
                // But only the latest game should be considered

                // The game is started
                if ($gameObj->started && isset($gameObj->fileTime) && ($now - $gameObj->fileTime->getTimestamp(
                    )) <= $this->gameStartedTime) {
                    $logger->debug('Game is started');
                }
                // The game is loaded
                if (!$gameObj->started && isset($gameObj->fileTime) && ($now - $gameObj->fileTime->getTimestamp(
                    )) <= $this->gameLoadedTime) {
                    $logger->debug('Game is loaded');
                }
                return $this->respond(new ErrorDto('Game is not finished', type: ErrorType::VALIDATION), 400);
            }

            // Check players
            $null = true;
            /** @var Player $player */
            foreach ($gameObj->getPlayers() as $player) {
                if ($player->score !== 0 || $player->shots !== 0) {
                    $null = false;
                    break;
                }
            }
            if ($null) {
                $logger->warning('Game is empty');
                // Empty game - no shots, no hits, etc..
                return $this->respond(new ErrorDto('Game is empty', type: ErrorType::VALIDATION), 400);
            }

            if (!$gameObj->save()) {
                throw new ResultsParseException('Failed saving game into DB.');
            }
        } catch (Exception $e) {
            return $this->respond(
              new ErrorDto('Error while parsing game file.', type: ErrorType::INTERNAL, exception: $e),
              500
            );
        }
        return $this->respond(['success' => true]);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    #[OA\Get('/api/results/last')]
    public function getLastGameFile(Request $request) : ResponseInterface {
        $resultsDir = urldecode($request->getGet('dir', ''));
        if (empty($resultsDir)) {
            return $this->respond(
              new ErrorDto(
                      'Missing required argument "dir". Valid results directory is expected.',
                type: ErrorType::VALIDATION
              ),
              400
            );
        }
        $resultsDir = trailingSlashIt($resultsDir);
        /** @var string[][] $resultFiles */
        $resultFilesAll = [];
        foreach (GameFactory::getSupportedSystems() as $system) {
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\'.ucfirst($system).'\\ResultsParser';
            if (!class_exists($class)) {
                continue;
            }
            $resultFilesAll[] = glob(ROOT.$resultsDir.$class::getFileGlob());
        }

        /** @var string[] $resultFiles */
        $resultFiles = array_unique(array_merge(...$resultFilesAll));

        // Sort by time
        usort($resultFiles, static fn(string $a, string $b) => filemtime($b) - filemtime($a));

        /** @var string $resultsContent1 */
        $resultsContent1 = file_get_contents($resultFiles[0]);
        /** @var string $resultsContent2 */
        $resultsContent2 = file_get_contents($resultFiles[1]);
        return $this->respond(
          [
            'files'     => $resultFiles,
            'contents1' => utf8_encode($resultsContent1),
            'contents2' => utf8_encode($resultsContent2),
          ]
        );
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws ArchiveCreationException
     * @throws JsonException
     */
    #[OA\Get('/api/results/download')]
    public function downloadLastGameFiles(Request $request) : ResponseInterface {
        /** TODO: Secure.. this is really bad.. and would allow for downloading of any files */
        $resultsDir = urldecode($request->getGet('dir', ''));
        if (empty($resultsDir)) {
            return $this->respond(
              new ErrorDto(
                      'Missing required argument "dir". Valid results directory is expected.',
                type: ErrorType::VALIDATION
              ),
              400
            );
        }
        $resultsDir = trailingSlashIt($resultsDir);
        /** @var string[][] $resultFiles */
        $resultFilesAll = [];
        foreach (GameFactory::getSupportedSystems() as $system) {
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\'.ucfirst($system).'\\ResultsParser';
            if (!class_exists($class)) {
                continue;
            }
            $resultFilesAll[] = glob(ROOT.$resultsDir.$class::getFileGlob());
        }
        /** @var string[] $resultFiles */
        $resultFiles = array_unique(array_merge(...$resultFilesAll));

        $archive = new ZipArchive();
        $test = $archive->open(TMP_DIR.'games.zip', ZipArchive::CREATE); // Create or open a zip file
        if ($test !== true) {
            throw new ArchiveCreationException($test);
        }

        foreach ($resultFiles as $file) {
            $fileName = str_replace(ROOT.$resultsDir, '', $file);
            $archive->addFile($file, $fileName);
        }
        $archive->close();

        return new Response(
          new \Nyholm\Psr7\Response(
            headers: [
                       'Content-Type'        => 'application/zip',
                       'Content-Disposition' => 'attachment; filename="games.zip"',
                       'Content-Length'      => (string) filesize(TMP_DIR.'games.zip'),
                     ],
            body   : fopen(TMP_DIR.'games.zip', 'rb')
          )
        );
    }

}