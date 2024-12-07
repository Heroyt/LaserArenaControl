<?php
declare(strict_types=1);
namespace App\Templates\Public;

use App\GameModels\Game\Game;
use App\Templates\AutoFillParameters;
use Lsr\Core\Controllers\TemplateParameters;

class GamesDetailTemplate extends TemplateParameters
{
    use AutoFillParameters;

    public string $publicUrl;
    public string $code;
    public Game $game;
    public string $qr;

}