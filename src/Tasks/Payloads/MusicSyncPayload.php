<?php
declare(strict_types=1);

namespace App\Tasks\Payloads;

use App\Models\MusicMode;
use Lsr\Roadrunner\Tasks\TaskPayloadInterface;

readonly class MusicSyncPayload implements TaskPayloadInterface
{

    public function __construct(
      public ?MusicMode $music = null,
    ) {}

}