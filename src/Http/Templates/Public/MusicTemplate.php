<?php

declare(strict_types=1);

namespace App\Http\Templates\Public;

use App\Gate\Models\MusicGroupDto;
use Lsr\Core\Controllers\TemplateParameters;

class MusicTemplate extends TemplateParameters
{
    /** @var MusicGroupDto[] */
    public array $music = [];
}
