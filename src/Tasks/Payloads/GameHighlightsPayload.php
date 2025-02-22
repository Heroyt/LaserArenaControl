<?php

namespace App\Tasks\Payloads;

use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class GameHighlightsPayload implements TaskPayloadInterface
{
    public function __construct(
      public ?string $code = null,
    ) {}
}
