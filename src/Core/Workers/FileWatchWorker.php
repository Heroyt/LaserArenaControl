<?php
declare(strict_types=1);

namespace App\Core\Workers;

use App\Tasks\GameImportTask;
use App\Tasks\Payloads\GameImportPayload;
use Lsr\Core\App;
use Lsr\Logging\Logger;
use Lsr\Roadrunner\Tasks\TaskProducer;
use Lsr\Roadrunner\Workers\Worker;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker as RrWorker;
use Throwable;

class FileWatchWorker implements Worker
{

    public App $app {
        get {
            if (!isset($this->app)) {
                $this->app = App::getInstance();
            }
            return $this->app;
        }
        set(App $value) => $this->app = $value;
    }
    private Logger $logger {
        get {
            if (!isset($this->logger)) {
                $this->logger = new Logger(LOG_DIR, 'worker-file-watch');
            }
            return $this->logger;
        }
        set(Logger $value) => $this->logger = $value;
    }
    private RrWorker $worker;

    public function __construct(
      private readonly TaskProducer $taskProducer,
      private readonly Metrics      $metrics,
    ) {
        $this->worker = RrWorker::create();
    }

    public function run() : void {
        while ($payload = $this->worker->waitPayload()) {
            try {
                $this->logger->debug('file_watch: '.$payload->body);

                // Parse payload
                /** @var array{directory?:string,eventTime?:string,file?:string,op?:string,path?:string} $data */
                $data = json_decode($payload->body, true, 512, JSON_THROW_ON_ERROR);
                $dir = (string) ($data['directory'] ?? '');
                if (empty($dir)) {
                    $this->logger->error('Missing required argument "directory". Valid results directory is expected.');
                    $this->worker->respond(new Payload('ERROR'));
                }

                // Plan import on watched dir
                $this->metrics->add('import_planned', 1, ['file_watch']);
                $this->taskProducer->push(
                  GameImportTask::class,
                  new GameImportPayload($dir),
                  new Options(priority: GameImportTask::PRIORITY),
                );
                $this->worker->respond(new Payload('OK'));
            } catch (Throwable $e) {
                $this->handleError($e);
            }
        }
    }

    public function handleError(Throwable $error) : void {
        $this->logger->exception($error);
        $this->worker->respond(new Payload('ERROR'));
    }
}