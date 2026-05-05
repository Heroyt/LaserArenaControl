<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\SystemType;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class CreateSystemData
{
    #[Required, StringLength(min: 3)]
    public string $name;

    #[Required]
    public SystemType $type;

    #[Required, IntRange(min: 1)]
    public int $vests;
}
