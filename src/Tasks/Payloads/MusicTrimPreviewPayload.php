<?php

namespace App\Tasks\Payloads;

readonly class MusicTrimPreviewPayload
{

    public function __construct(
      public int $musicModeId
    ) {}

}