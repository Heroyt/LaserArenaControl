<?php
declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\ClearGroupsCommand;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use Spiral\RoadRunner\Metrics\Metrics;

final readonly class ClearGroupsJob implements Job
{

    public function __construct(
      private CommandBus $commandBus,
      private Metrics    $metrics,
    ) {}

    public function getName() : string {
        return 'Clear Groups';
    }

    public function run(JobLock $lock) : void {
        $this->metrics->add('cron_job_started', 1, ['clear_groups']);
        $lock->refresh(60.0);
        $response = $this->commandBus->dispatch(new ClearGroupsCommand());
        new Logger(LOG_DIR, 'cron')
          ->debug(
            'Cleared old groups',
            ['deleted' => $response->deleted, 'hidden' => $response->hidden]
          );
        $this->metrics->add('cron_job_ok', 1, ['clear_groups']);
    }
}