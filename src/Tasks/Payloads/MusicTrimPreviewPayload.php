<?php

declare(strict_types=1);

namespace App\Tasks\Payloads;

use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class MusicTrimPreviewPayload implements TaskPayloadInterface
{
    public function __construct(
        public int $musicModeId,
    ) {
    }
}
