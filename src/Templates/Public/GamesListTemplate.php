<?php

declare(strict_types=1);

namespace App\Templates\Public;

use App\GameModels\Game\Game;
use App\Templates\AutoFillParameters;
use DateTimeInterface;
use Lsr\Core\Controllers\TemplateParameters;

class GamesListTemplate extends TemplateParameters
{
    use AutoFillParameters;

    public DateTimeInterface $date;
    /** @var Game[] */
    public array $games;
}
