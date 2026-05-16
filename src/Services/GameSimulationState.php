<?php

declare(strict_types=1);

namespace App\Services;

enum GameSimulationState: string
{
    case FINISHED = 'finished';
    case LOADED = 'loaded';
    case STARTED = 'started';
}
