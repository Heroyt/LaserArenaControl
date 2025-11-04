<?php

declare(strict_types=1);

namespace App\Http\Templates\Public;

use App\GameModels\Game\Game;
use App\Http\Templates\AutoFillParameters;
use DateTimeInterface;
use Lsr\Core\Controllers\TemplateParameters;

class GamesListTemplate extends TemplateParameters
{
    use AutoFillParameters;

    public DateTimeInterface $date;

    /**
     * @var Game[]
     * @phpstan-ignore missingType.generics
     */
    public array $games;
}
