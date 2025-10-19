<?php

declare(strict_types=1);

namespace App\Templates\Settings;

use App\GameModels\Vest;
use App\Models\System;
use App\Templates\AutoFillParameters;
use Lsr\Core\Controllers\TemplateParameters;

class SystemsSettingsParameters extends TemplateParameters
{
    use AutoFillParameters;

    /** @var System[] */
    public array $systems = [];

    /** @var array<int, Vest[]> */
    public array $vests = [];
    /** @var Vest[][][] Vest at [System][Row][Column] */
    public array $vestsGrid = [];
    /** @var int[] */
    public array $columnCounts = [];
    /** @var int[] */
    public array $rowCounts = [];
}
