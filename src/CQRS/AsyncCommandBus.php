<?php

declare(strict_types=1);

namespace App\CQRS;

use App\Tasks\HandleCommandTask;
use App\Tasks\Payloads\HandleCommandPayload;
use Lsr\CQRS\AsyncCommandBusInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Roadrunner\Tasks\TaskProducer;
use Spiral\RoadRunner\Jobs\Exception\JobsException;

readonly class AsyncCommandBus implements AsyncCommandBusInterface
{
    public function __construct(
      private TaskProducer $taskProducer,
    ) {}

    /**
     * @throws JobsException
     */
    public function dispatch(CommandInterface $command) : void {
        $this->taskProducer->push(HandleCommandTask::class, new HandleCommandPayload($command));
    }
}
