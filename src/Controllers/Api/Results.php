<?php

namespace App\Controllers\Api;

use App\Api\Response\Results\LastResultsResponse;
use App\GameModels\Factory\GameFactory;
use App\Services\ImportService;
use App\Tasks\GameImportTask;
use App\Tasks\Payloads\GameImportPayload;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Lg\Results\Interface\ResultsParserInterface;
use Lsr\Roadrunner\Tasks\TaskProducer;
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
    public function __construct(
      private readonly ImportService $importService,
      private readonly TaskProducer  $taskProducer,
      private readonly Metrics       $metrics,
    ) {}

    #[OA\Post(
      path       : '/api/results/import',
      operationId: 'importResults',
      description: 'Import results from a directory. Pushes a job to the queue by default.',
      requestBody: new OA\RequestBody(
        required: true,
        content : new OA\JsonContent(
                    required  : ["dir"],
                    properties: [
                                  new OA\Property(
                                    property   : "dir",
                                    description: 'Directory to import from',
                                    type       : "string",
                                    example    : 'lmx/results'
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
      response   : 200,
      description: 'Success response',
      content    : new OA\JsonContent(
        oneOf: [
                 new OA\Schema(ref: '#/components/schemas/SuccessResponse'),
                 new OA\Schema(ref: '#/components/schemas/ImportResponse'),
               ]
      )
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function import(Request $request) : ResponseInterface {
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
            }
            else {
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
      path       : '/api/results/import/{game}',
      operationId: 'importGameResults',
      description: 'Import results for 1 game.',
      tags       : ['Import']
    )]
    #[OA\Parameter(name: 'game', description: 'Game code', in: 'path', required: true)]
    #[OA\Response(
      response   : 200,
      description: 'Success response',
      content    : new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
    )]
    #[OA\Response(
      response   : 404,
      description: 'Game (file) not found',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
      response   : 417,
      description: 'Cannot get game file number',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function importGame(Request $request, string $game = '') : ResponseInterface {
        /** @var string $dir */
        $dir = $request->getPost('dir', DEFAULT_RESULTS_DIR);
        assert($dir !== '', 'Invalid results directory');
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

        $response = $this->importService->importGame($gameObj, $resultsDir);
        $responseCode = $response instanceof SuccessResponse ? 200 : match ($response->type) {
            ErrorType::VALIDATION                    => 400,
            ErrorType::DATABASE, ErrorType::INTERNAL => 500,
            ErrorType::NOT_FOUND                     => 404,
            ErrorType::ACCESS                        => 403,
        };
        return $this->respond($response, $responseCode);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     */
    #[OA\Get(
      path       : '/api/results/last',
      operationId: 'getLastGameFile',
      description: 'Get last game file from results directory.',
      tags       : ['Import']
    )]
    #[OA\Parameter(name: 'dir', description: 'Results directory', in: 'query', required: true, example: 'lmx/results')]
    #[OA\Response(
      response   : 200,
      description: 'Last results data',
      content    : new OA\JsonContent(ref: '#/components/schemas/LastResultsResponse')
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    public function getLastGameFile(Request $request) : ResponseInterface {
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
            $class = 'App\\Tools\\ResultParsing\\'.ucfirst($system).'\\ResultsParser';
            if (!class_exists($class)) {
                continue;
            }
            $files = glob(ROOT.$resultsDir.$class::getFileGlob());
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
