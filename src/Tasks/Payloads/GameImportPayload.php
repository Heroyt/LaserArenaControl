<?php

namespace App\Tasks\Payloads;

use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class GameImportPayload implements TaskPayloadInterface
{
    public function __construct(
      public string $dir,
    ) {}
}
