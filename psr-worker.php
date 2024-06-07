<?php

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Controllers\E404;
use App\Controllers\E500;
use App\Core\App;
use App\Services\TaskProducer;
use App\Tasks\GameImportTask;
use App\Tasks\Payloads\GameImportPayload;
use App\Tasks\TaskDispatcherInterface;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\RequestFactory;
use Lsr\Logging\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Tracy\Debugger;
use Tracy\Helpers;
use Tracy\ILogger;

const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;
const ROADRUNNER = true;

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('display_startup_errors', '1');

require_once ROOT."include/load.php";

$app = App::getInstance();
$env = Environment::fromGlobals();

Debugger::$logDirectory = LOG_DIR.'tracy';

if (!file_exists(Debugger::$logDirectory) && !mkdir(Debugger::$logDirectory, 0777, true) && !is_dir(
    Debugger::$logDirectory
  )) {
    Debugger::$logDirectory = LOG_DIR;
}

switch ($env->getMode()) {
    case Mode::MODE_JOBS:
        $consumer = new Consumer();
        /** @var Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface $task */
        while ($task = $consumer->waitTask()) {
            try {
                $name = $task->getName();

                /** @var TaskDispatcherInterface $dispatcher */
                $dispatcher = $app::getService($name);
                $dispatcher->process($task);

                if (!$task->isCompleted()) {
                    $task->complete();
                }
            } catch (Throwable $e) {
                $task->fail($e);
            }
            $app->translations->updateTranslations();
        }
        break;
    case 'file_watch':
        $worker = Worker::create();
        $logger = new Logger(LOG_DIR, 'worker');
        /** @var TaskProducer $taskProducer */
        $taskProducer = App::getService('taskProducer');

        while ($payload = $worker->waitPayload()) {
            try {
                $logger->debug('file_watch: '.$payload->body);

                // Parse payload
                /** @var array{directory?:string,eventTime?:string,file?:string,op?:string,path?:string} $data */
                $data = json_decode($payload->body, true, 512, JSON_THROW_ON_ERROR);
                $dir = (string) ($data['directory'] ?? '');
                if (empty($resultsDir)) {
                    $logger->error('Missing required argument "directory". Valid results directory is expected.');
                    $worker->respond(new Payload('ERROR'));
                }

                // Plan import on watched dir
                $taskProducer->push(GameImportTask::class, new GameImportPayload($dir));
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
        $e500 = $app::getServiceByType(E500::class);

        while (true) {
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

                try {
                    $app->setRequest($request);

                    //var_dump('Request: '.$request->getUri());
                    //var_dump('App: '.$app->getRequest()->getUri());

                    $session = $app->session;
                    if (!$session->isInitialized()) {
                        $session->init();
                    }

                    $psr7->respond($app->run()->withAddedHeader('Content-Language', $app->translations->getLang()));
                    $session->close();
                    $app->translations->updateTranslations();
                } catch (RouteNotFoundException $e) { // 404 error
                    if (isset($e404)) {
                        $psr7->respond($e404->show($request, $e));
                    }
                    elseif (in_array('application/json', getAcceptTypes($request))) {
                        $psr7->respond(
                          new Response(
                            404, ['Content-Type' => 'application/json'], json_encode(
                                 new ErrorDto(
                                              'Route not found',
                                   type     : ErrorType::NOT_FOUND,
                                   detail   : $e->getMessage(),
                                   exception: $e
                                 ),
                                 JSON_THROW_ON_ERROR
                               )
                          )
                        );
                    }
                    else {
                        $psr7->respond(new Response(404, [], $e->getMessage()));
                    }
                    $psr7->getWorker()->error((string) $e);
                    continue;
                } catch (Throwable $e) {
                    $logger->exception($e);
                    Helpers::improveException($e);
                    Debugger::log($e, ILogger::EXCEPTION);

                    if (in_array('application/json', getAcceptTypes($request))) {
                        $psr7->respond(
                          new Response(
                            500, ['Content-Type' => 'application/json'], json_encode(
                                 new ErrorDto('Something Went wrong!', detail: $e->getMessage(), exception: $e),
                                 JSON_THROW_ON_ERROR
                               )
                          )
                        );
                        $psr7->getWorker()->error((string) $e);
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
                             500, [
                            'Content-Type' => 'text/html',
                          ], $blueScreen
                          )
                        );
                        file_put_contents('php://stderr', (string) $e);
                        continue;
                    }

                    if (isset($e500)) {
                        $e500->init($request);
                        $psr7->respond($e500->show($request, $e));
                    }
                    else {
                        $psr7->respond(new Response(500, [], $e->getMessage()));
                    }

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

/**
 * @param  ServerRequestInterface  $request
 * @return string[]
 */
function getAcceptTypes(ServerRequestInterface $request) : array {
    $types = [];
    foreach ($request->getHeader('Accept') as $value) {
        $types[] = strtolower(trim(explode(';', $value, 2)[0]));
    }
    return $types;
}