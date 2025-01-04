<?php

namespace App\Services;

use App\Tasks\TaskDispatcherInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\Queue;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;

/**
 *
 */
class TaskProducer
{
    /** @var PreparedTaskInterface[] */
    private array $planned = [];

    public function __construct(private readonly Queue $queue) {}

    /**
     * @param  class-string<TaskDispatcherInterface>  $dispatcher
     * @param  object  $payload
     * @param  OptionsInterface|null  $options
     * @return void
     * @throws JobsException
     */
    public function push(string $dispatcher, ?object $payload, ?OptionsInterface $options = null) : void {
        $this->queue->push(
          $dispatcher::getDiName(),
          igbinary_serialize($payload) ?? '',
          $options,
        );
    }

    /**
     * @param  class-string<TaskDispatcherInterface>  $dispatcher
     * @param  object  $payload
     * @param  OptionsInterface|null  $options
     * @return PreparedTaskInterface
     */
    public function plan(
      string            $dispatcher,
      ?object           $payload,
      ?OptionsInterface $options = null
    ) : PreparedTaskInterface {
        $task = $this->queue->create(
          $dispatcher::getDiName(),
          igbinary_serialize($payload) ?? '',
          $options,
        );
        $this->planned[] = $task;
        return $task;
    }

    /**
     * @return void
     * @throws JobsException
     */
    public function dispatch() : void {
        $this->queue->dispatchMany(...$this->planned);
        $this->planned = [];
    }
}
