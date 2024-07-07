<?php

namespace App\Api\DataObjects\Vests;

use App\GameModels\Game\Enums\VestStatus;

class LigaVest
{
    public string $vestNum;
    public string $system;
    public VestStatus $status;
    public ?string $info = null;
    public \DateTimeImmutable $updatedAt;
}
