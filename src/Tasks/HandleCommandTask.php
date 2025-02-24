<?php
declare(strict_types=1);

namespace App\Tasks;

use App\Tasks\Payloads\HandleCommandPayload;
use Lsr\CQRS\CommandBus;
use Lsr\Roadrunner\Tasks\TaskDispatcherInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

readonly class HandleCommandTask implements TaskDispatcherInterface
{

    public function __construct(
      private CommandBus $commandBus
    ) {}

    /**
     * @inheritDoc
     */
    public static function getDiName() : string {
        return 'task.handleCommand';
    }

    public function process(ReceivedTaskInterface $task, ?TaskPayloadInterface $payload = null) : void {
        if (!($payload instanceof HandleCommandPayload)) {
            $task->nack('Invalid payload');
            return;
        }

        $this->commandBus->dispatch($payload->command);
    }
}