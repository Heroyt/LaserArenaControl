<?php

declare(strict_types=1);

namespace App\Http\Templates\Settings;

use App\GameModels\Vest;
use App\Http\Templates\AutoFillParameters;
use App\Models\System;
use Lsr\Core\Controllers\TemplateParameters;

class SystemsSettingsParameters extends TemplateParameters
{
    use AutoFillParameters;

    /** @var System[] */
    public array $systems = [];

    /** @var Vest */
    public array $vests = [];
    /** @var Vest[][][] Vest at [System][Row][Column] */
    public array $vestsGrid = [];
    /** @var int[] */
    public array $columnCounts = [];
    /** @var int[] */
    public array $rowCounts = [];
}
