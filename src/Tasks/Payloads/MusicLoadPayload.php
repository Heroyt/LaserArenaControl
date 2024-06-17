<?php

namespace App\Tasks\Payloads;

readonly class MusicLoadPayload
{

    public function __construct(
      public int    $musicId,
      public string $musicFile,
      public string $loader,
      public string $system = 'evo5',
    ) {}

}