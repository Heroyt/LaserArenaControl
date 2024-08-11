<?php

namespace App\Controllers\Api;

use App\Api\Response\Results\LastResultsResponse;
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
use Lsr\Core\Config;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Logger;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;

/**
 *
 */
class Results extends ApiController
{
    private int $gameLoadedTime;
    private int $gameStartedTime;

    public function __construct(
        private readonly PlayerProvider $playerProvider,
        private readonly ImportService  $importService,
        Config                          $config,
        private readonly TaskProducer   $taskProducer,
        private readonly Metrics $metrics,
    ) {
        parent::__construct();
        $this->gameLoadedTime = (int) ($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
        $this->gameStartedTime = (int) ($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
    }

    #[OA\Post(
        path: '/api/results/import',
        operationId: 'importResults',
        description: 'Import results from a directory. Pushes a job to the queue by default.',
        requestBody: new OA\RequestBody(
            required: true,
            content : new OA\JsonContent(
                required  : ["dir"],
                properties: [
                              new OA\Property(
                                  property: "dir",
                                  description: 'Directory to import from',
                                  type: "string",
                                  example: 'lmx/results'
                              ),
                              new OA\Property(
                                  property   : "sync",
                                  description: 'If present, import games immediately.',
                                  type       : "boolean",
                                  example    : 'true'
                              ),
                ],
                type      : 'object',
            ),
        ),
        tags       : ['Import']
    )]
    #[OA\Response(
        response: 200,
        description: 'Success response',
        content: new OA\JsonContent(
            oneOf: [
                      new OA\Schema(ref: '#/components/schemas/SuccessResponse'),
                      new OA\Schema(ref: '#/components/schemas/ImportResponse'),
                   ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Request error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function import(Request $request): ResponseInterface {
        $resultsDir = $request->getPost('dir', '');
        assert(is_string($resultsDir), 'Import directory must be a string');

        if (empty($resultsDir)) {
            return $this->respond(
                new ErrorResponse(
                    'Missing required argument "dir". Valid results directory is expected.',
                    type: ErrorType::VALIDATION
                ),
                400
            );
        }

        $sync = $request->getPost('sync');
        try {
            if (!empty($sync)) {
                $response = $this->importService->import($resultsDir);
                if ($response instanceof ErrorResponse) {
                    $this->respond($response, 500);
                }
                $this->respond($response);
            } else {
                $this->metrics->add('import_planned', 1, ['api']);
                $this->taskProducer->push(
                    GameImportTask::class,
                    new GameImportPayload($resultsDir),
                    new Options(priority: GameImportTask::PRIORITY),
                );
            }
        } catch (JobsException $e) {
            $this->respond(new ErrorResponse('Job push failed', exception: $e), 500);
        } catch (Throwable $e) {
            $this->respond(new ErrorResponse('Internal error', exception: $e), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    /**
     * Import one game (again)
     *
     * @param  Request  $request
     * @param  string  $game
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/results/import/{game}',
        operationId: 'importGameResults',
        description: 'Import results for 1 game.',
        tags       : ['Import']
    )]
    #[OA\Parameter(name: 'game', description: 'Game code', in: 'path', required: true)]
    #[OA\Response(
        response: 200,
        description: 'Success response',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
    )]
    #[OA\Response(
        response: 404,
        description: 'Game (file) not found',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 417,
        description: 'Cannot get game file number',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function importGame(Request $request, string $game = ''): ResponseInterface {
        $logger = new Logger(LOG_DIR . 'results/', 'import');
        $dir = $request->getPost('dir', DEFAULT_RESULTS_DIR);
        assert(is_string($dir) && $dir !== '', 'Invalid results directory');
        $resultsDir = trailingSlashIt($dir);

        try {
            $gameObj = GameFactory::getByCode($game);
        } catch (Throwable $e) {
            return $this->respond(
                new ErrorResponse(
                    'Error while getting the game by code.',
                    type     : ErrorType::INTERNAL,
                    exception: $e
                ),
                500
            );
        }
        if (!isset($gameObj)) {
            return $this->respond(
                new ErrorResponse('Unknown game.', type: ErrorType::NOT_FOUND),
                404
            );
        }

        $gameObj->clearCache();

        if (isset($gameObj->resultsFile) && file_exists($gameObj->resultsFile)) {
            $file = $gameObj->resultsFile;
        } else if ($gameObj instanceof Game && !empty($gameObj->fileNumber)) {
            $files = glob($resultsDir . str_pad((string) $gameObj->fileNumber, 4, '0', STR_PAD_LEFT) . '*.game');
            if (empty($files)) {
                return $this->respond(
                    new ErrorResponse(
                        'Cannot find game file.',
                        type  : ErrorType::NOT_FOUND,
                        values: ['path' => $resultsDir . $gameObj->fileNumber . '*.game']
                    ),
                    404
                );
            }
            if (count($files) > 1) {
                return $this->respond(
                    new ErrorResponse(
                        'Found more than one suitable game file.',
                        type  : ErrorType::INTERNAL,
                        values: ['path' => $resultsDir . $gameObj->fileNumber . '*.game', 'files' => $files]
                    ),
                    500
                );
            }
            $file = $files[0];
        } else {
            return $this->respond(
                new ErrorResponse(
                    'Cannot get game file number.',
                    type  : ErrorType::NOT_FOUND,
                    values: ['game' => $gameObj]
                ),
                417,
            );
        }

        try {
            $logger->info('Importing file: ' . $file);
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\' . ucfirst($gameObj::SYSTEM) . '\\ResultsParser';
            if (!class_exists($class)) {
                return $this->respond(
                    new ErrorResponse('No parser for this game (' . $gameObj::SYSTEM . ')', type: ErrorType::INTERNAL),
                    500,
                );
            }
            if (!$class::checkFile($file)) {
                return $this->respond(
                    new ErrorResponse('Game file cannot be parsed: ' . $file, type: ErrorType::INTERNAL),
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
                if (
                    $gameObj->started && isset($gameObj->fileTime) && ($now - $gameObj->fileTime->getTimestamp(
                    )) <= $this->gameStartedTime
                ) {
                    $logger->debug('Game is started');
                }
                // The game is loaded
                if (
                    !$gameObj->started && isset($gameObj->fileTime) && ($now - $gameObj->fileTime->getTimestamp(
                    )) <= $this->gameLoadedTime
                ) {
                    $logger->debug('Game is loaded');
                }
                return $this->respond(new ErrorResponse('Game is not finished', type: ErrorType::VALIDATION), 400);
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
                return $this->respond(new ErrorResponse('Game is empty', type: ErrorType::VALIDATION), 400);
            }

            if (!$gameObj->save()) {
                throw new ResultsParseException('Failed saving game into DB.');
            }
        } catch (Exception $e) {
            return $this->respond(
                new ErrorResponse('Error while parsing game file.', type: ErrorType::INTERNAL, exception: $e),
                500
            );
        }
        return $this->respond(new SuccessResponse());
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     */
    #[OA\Get(
        path: '/api/results/last',
        operationId: 'getLastGameFile',
        description: 'Get last game file from results directory.',
        tags       : ['Import']
    )]
    #[OA\Parameter(name: 'dir', description: 'Results directory', in: 'query', required: true, example: 'lmx/results')]
    #[OA\Response(
        response: 200,
        description: 'Last results data',
        content: new OA\JsonContent(ref: '#/components/schemas/LastResultsResponse')
    )]
    #[OA\Response(
        response: 400,
        description: 'Request error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function getLastGameFile(Request $request): ResponseInterface {
        $dir = $request->getGet('dir', '');
        assert(is_string($dir), 'Invalid input parameter');
        $resultsDir = urldecode($dir);
        if (empty($resultsDir)) {
            return $this->respond(
                new ErrorResponse(
                    'Missing required argument "dir". Valid results directory is expected.',
                    type: ErrorType::VALIDATION
                ),
                400
            );
        }
        $resultsDir = trailingSlashIt($resultsDir);
        $resultFilesAll = [];
        foreach (GameFactory::getSupportedSystems() as $system) {
            /** @var class-string<ResultsParserInterface> $class */
            $class = 'App\\Tools\\ResultParsing\\' . ucfirst($system) . '\\ResultsParser';
            if (!class_exists($class)) {
                continue;
            }
            $files = glob(ROOT . $resultsDir . $class::getFileGlob());
            assert(is_array($files), 'Glob failed');
            $resultFilesAll[] = $files;
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
            new LastResultsResponse(
                $resultFiles,
                utf8_encode($resultsContent1),
                utf8_encode($resultsContent2),
            )
        );
    }
}
