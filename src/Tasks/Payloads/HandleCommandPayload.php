<?php
declare(strict_types=1);

namespace App\Tasks\Payloads;

use Lsr\CQRS\CommandInterface;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class HandleCommandPayload implements TaskPayloadInterface
{

    public function __construct(
      public CommandInterface $command
    ) {}

}