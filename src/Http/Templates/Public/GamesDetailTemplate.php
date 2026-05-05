<?php

declare(strict_types=1);

namespace App\Http\Templates\Public;

use App\GameModels\Game\Game;
use App\Http\Templates\AutoFillParameters;
use Lsr\Core\Controllers\TemplateParameters;

class GamesDetailTemplate extends TemplateParameters
{
    use AutoFillParameters;

    /** @var non-empty-string */
    public string $publicUrl;
    /** @var non-empty-string */
    public string $code;
    /**
     * @var Game
     * @phpstan-ignore missingType.generics
     */
    public Game $game;
    /** @var non-empty-string */
    public string $qr;
}
