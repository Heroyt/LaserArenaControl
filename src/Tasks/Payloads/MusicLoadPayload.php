<?php

namespace App\Tasks\Payloads;

use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class MusicLoadPayload implements TaskPayloadInterface
{
    public function __construct(
      public int    $musicId,
      public string $musicFile,
      public string $loader,
      public string $system = 'evo5',
      public ?float $timeSinceStart = null,
    ) {}
}
