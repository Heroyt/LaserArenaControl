<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\SystemType;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class SystemData
{
    #[Required, StringLength(min: 3)]
    public string $name;

    #[Required]
    public SystemType $type;

    public string $ip = '';
    public string $results_dir = '';
    public string $load_dir = '';
    public string $music_dir = '';
    public bool $default = false;
    public bool $active = false;
    #[Required, IntRange(min: 1, max: 255)]
    public int $columns;
    #[Required, IntRange(min: 1, max: 255)]
    public int $rows;
}
