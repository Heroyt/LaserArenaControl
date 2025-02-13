<?php

use App\Controllers\E404;
use App\Controllers\E500;
use App\Core\App;
use App\Core\Info;
use App\Services\FontAwesomeManager;
use App\Services\TaskProducer;
use App\Tasks\GameImportTask;
use App\Tasks\Payloads\GameImportPayload;
use App\Tasks\TaskDispatcherInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\RequestFactory;
use Lsr\Logging\Logger;
use Lsr\Orm\ModelRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Tracy\Debugger;
use Tracy\Helpers;
use Tracy\ILogger;

const ROOT = __DIR__ . '/';
/** Visiting site normally */
const INDEX = true;

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('display_startup_errors', '1');

require_once ROOT . "include/load.php";

$app = App::getInstance();
$env = Environment::fromGlobals();

Debugger::$logDirectory = LOG_DIR . 'tracy';

if (
    !file_exists(Debugger::$logDirectory) &&
    !mkdir(Debugger::$logDirectory, 0777, true) &&
    !is_dir(Debugger::$logDirectory)
) {
    Debugger::$logDirectory = LOG_DIR;
}

switch ($env->getMode()) {
    case Mode::MODE_JOBS:
        $consumer = new Consumer();
        /** @var Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface $task */
        while ($task = $consumer->waitTask()) {
            // Clear static cache
            Info::clearStaticCache();
            ModelRepository::clearInstances();

            try {
                $name = $task->getName();

                /** @var TaskDispatcherInterface $dispatcher */
                $dispatcher = $app::getService($name);
                $dispatcher->process($task);

                if (!$task->isCompleted()) {
                    $task->ack();
                }
            } catch (Throwable $e) {
                $task->nack($e);
            }
            $app->translations->updateTranslations();
        }
        break;
    /** @noinspection PhpExpectedValuesShouldBeUsedInspection */
    case 'file_watch':
        $worker = Worker::create();
        $logger = new Logger(LOG_DIR, 'worker');
        /** @var TaskProducer $taskProducer */
        $taskProducer = App::getService('taskProducer');
        /** @var Metrics $metrics */
        $metrics = App::getService('metrics');

        while ($payload = $worker->waitPayload()) {
            try {
                $logger->debug('file_watch: ' . $payload->body);

                // Parse payload
                /** @var array{directory?:string,eventTime?:string,file?:string,op?:string,path?:string} $data */
                $data = json_decode($payload->body, true, 512, JSON_THROW_ON_ERROR);
                $dir = (string) ($data['directory'] ?? '');
                if (empty($resultsDir)) {
                    $logger->error('Missing required argument "directory". Valid results directory is expected.');
                    $worker->respond(new Payload('ERROR'));
                }

                // Plan import on watched dir
                $metrics->add('import_planned', 1, ['file_watch']);
                $taskProducer->push(
                    GameImportTask::class,
                    new GameImportPayload($dir),
                    new Options(priority: GameImportTask::PRIORITY),
                );
                $worker->respond(new Payload('OK'));
            } catch (Throwable $e) {
                $logger->exception($e);
                $worker->respond(new Payload('ERROR'));
            }
        }
        break;
    default:
        $worker = Worker::create();

        $logger = new Logger(LOG_DIR, 'worker');
        $factory = new Psr17Factory();
        $psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

        $e404 = $app::getServiceByType(E404::class);
        assert($e404 instanceof E404, 'Invalid controller instance from DI');
        $e500 = $app::getServiceByType(E500::class);
        assert($e500 instanceof E500, 'Invalid controller instance from DI');

        $fontawesome = $app::getService('fontawesome');
        assert($fontawesome instanceof FontAwesomeManager, 'Invalid fontawesome manager instance from DI');

        while (true) {
            // Clear static cache
            Info::clearStaticCache();
            ModelRepository::clearInstances();
            if (isset($request)) {
                unset($request);
            }

            try {
                try {
                    $request = $psr7->waitRequest();
                    if ($request === null) {
                        break;
                    }
                } catch (Throwable $e) {
                    // Although the PSR-17 specification clearly states that there can be
                    // no exceptions when creating a request, however, some implementations
                    // may violate this rule. Therefore, it is recommended to process the
                    // incoming request for errors.
                    //
                    // Send "Bad Request" response.
                    $psr7->respond(new Response(400));
                    continue;
                }

                $request = RequestFactory::fromPsrRequest($request);
                $app->setRequest($request);
                assert($request === $app->getRequest(), 'Request set does not match');

                echo $request->getUri();
                echo PHP_EOL;

                try {
                    $session = $app->session;
                    if (!$session->isInitialized()) {
                        $session->init();
                    }

                    $psr7->respond(
                        $app->run()
                          ->withAddedHeader('Content-Language', $app->translations->getLang())
                          ->withAddedHeader('Set-Cookie', $session->getCookieHeader())
                    );
                    $session->close();
                    $app->translations->updateTranslations();
                    $fontawesome->saveIcons();
                } catch (RouteNotFoundException $e) { // 404 error
                    if (isset($e404)) {
                        $e404->init($request);
                        $psr7->respond($e404->show($request, $e));
                    } elseif (in_array('application/json', getAcceptTypes($request))) {
                        $psr7->respond(
                            new Response(
                                404,
                                ['Content-Type' => 'application/json'],
                                json_encode(
                                    new ErrorResponse(
                                        'Route not found',
                                        type     : ErrorType::NOT_FOUND,
                                        detail   : $e->getMessage(),
                                        exception: $e
                                    ),
                                    JSON_THROW_ON_ERROR
                                )
                            )
                        );
                    } else {
                        $psr7->respond(new Response(404, [], $e->getMessage()));
                    }
                    continue;
                } catch (Throwable $e) {
                    $logger->exception($e);
                    Helpers::improveException($e);
                    Debugger::log($e, ILogger::EXCEPTION);

                    if (in_array('application/json', getAcceptTypes($request))) {
                        $psr7->respond(
                            new Response(
                                500,
                                ['Content-Type' => 'application/json'],
                                json_encode(
                                    new ErrorResponse('Something Went wrong!', detail: $e->getMessage(), exception: $e),
                                    JSON_THROW_ON_ERROR
                                )
                            )
                        );
                        continue;
                    }

                    if (!$app->isProduction()) {
                        ob_start(); // double buffer prevents sending HTTP headers in some PHP
                        ob_start();
                        Debugger::getBlueScreen()->render($e);
                        /** @var string $blueScreen */
                        $blueScreen = ob_get_clean();
                        ob_end_clean();

                        $psr7->respond(
                            new Response(
                                500,
                                [
                                'Content-Type' => 'text/html',
                                ],
                                $blueScreen
                            )
                        );
                        file_put_contents('php://stderr', (string) $e);
                        continue;
                    }

                    $e500->init($request);
                    $psr7->respond($e500->show($request, $e));

                    file_put_contents('php://stderr', (string) $e);
                }
            } catch (Throwable $e) { // Last line of defence if any error occurs
                // Log exception
                $logger->exception($e);
                Helpers::improveException($e);
                Debugger::log($e, ILogger::EXCEPTION);

                // Inform worker that an unexpected error occured
                $psr7->respond(new Response(500, [], $e->getMessage()));
                file_put_contents('php://stderr', (string) $e);
            }
        }
        break;
}
