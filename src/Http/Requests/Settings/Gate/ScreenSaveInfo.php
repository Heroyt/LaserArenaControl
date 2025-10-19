<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Gate;

use App\Gate\Logic\ScreenTriggerType;
use Symfony\Component\Serializer\Attribute\SerializedName;

class ScreenSaveInfo
{
    /** @var string|null */
    public ?string $type = null;
    public ?ScreenTriggerType $trigger = null;
    public ?int $order = null;
    #[SerializedName('trigger_value')]
    public ?string $triggerValue = null;
    /** @var array<string,mixed>|null */
    public ?array $settings = null;
}
