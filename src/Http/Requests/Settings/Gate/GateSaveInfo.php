<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Gate;

use Symfony\Component\Serializer\Attribute\SerializedName;

class GateSaveInfo
{
    public ?string $name = null;
    public ?string $slug = null;

    /** @var array<int, ScreenSaveInfo> */
    public array $screen = [];

    /** @var array<string|int, ScreenSaveInfo> */
    #[SerializedName('new-screen')]
    public array $newScreen = [];

    /** @var int[] */
    #[SerializedName('delete-screens')]
    public array $deleteScreen = [];
}
