<?php

declare(strict_types=1);

namespace App\Templates\Public;

use App\Models\Auth\Player;
use Lsr\Core\Controllers\TemplateParameters;

class LaserLigaTemplate extends TemplateParameters
{
    /** @var array{name?:string,email?:string} */
    public array $registerValues = [];

    public ?Player $newPlayer = null;
    public string $url;
    public string $qr;
}
