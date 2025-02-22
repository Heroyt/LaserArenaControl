<?php

namespace App\Tasks\Payloads;

use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class GamePrecachePayload implements TaskPayloadInterface
{
    public function __construct(
      public ?string $code = null,
      public ?int    $style = null,
      public ?string $template = null,
    ) {}
}
