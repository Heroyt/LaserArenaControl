<?php

use App\Core\App;
use App\Tasks\TaskDispatcherInterface;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\RequestFactory;
use Lsr\Interfaces\SessionInterface;
use Lsr\Logging\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Worker;
use Tracy\Debugger;

const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;
const ROADRUNNER = true;

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('display_startup_errors', '1');

require_once ROOT."include/load.php";

$env = Environment::fromGlobals();
$isJobsMode = $env->getMode() === Mode::MODE_JOBS;

if ($isJobsMode) {
	$consumer = new Consumer();
	/** @var Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface $task */
	while ($task = $consumer->waitTask()) {
		try {
			$name = $task->getName();

			/** @var TaskDispatcherInterface $dispatcher */
			$dispatcher = App::getService($name);
			$dispatcher->process($task);

			if (!$task->isCompleted()) {
				$task->complete();
			}
		} catch (Throwable $e) {
			$task->fail($e);
		}
	}
}
else {
	$worker = Worker::create();

	$logger = new Logger(LOG_DIR, 'worker');
	$factory = new Psr17Factory();
	$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

	while (true) {
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

		try {
			$request = RequestFactory::fromPsrRequest($request);
			App::setRequest($request);

			var_dump('Request: '.$request->getUri());
			var_dump('App: '.App::getRequest()->getUri());

			/** @var SessionInterface $session */
			$session = App::getService('session');
			if (!$session->isInitialized()) {
				$session->init();
			}

			Debugger::$usePsr7 = true;
			//Debugger::enable(PRODUCTION ? Debugger::Production : Debugger::Development, LOG_DIR);

			/*$get = $request->getQueryParams();
			if (isset($get['_tracy_bar'])) {
				$_GET = $get;
				ob_start();
				Debugger::dispatchPsr7($request);
				$content = ob_get_clean();
				$psr7->respond(new Response(200, [], $content));
			}
			else {*/
			$response = App::run();
			$psr7->respond($response);
			//}
			$session->close();
		} catch (Throwable $e) {
			// In case of any exceptions in the application code, you should handle
			// them and inform the client about the presence of a server error.
			//
			// Reply by the 500 Internal Server Error response
			$psr7->respond(new Response(500, [], 'Something Went Wrong!'));

			// Additionally, we can inform the RoadRunner that the processing
			// of the request failed.
			$psr7->getWorker()->error((string) $e);
			$logger->exception($e);
		}
	}
}