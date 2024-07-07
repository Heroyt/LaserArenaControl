<?php

namespace App\Api\DataObjects\LigaPlayer;

use App\Models\Auth\Enums\ConnectionType;

class LigaPlayerConnection
{
    public ConnectionType $type;
    public string $identifier;
}
