<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Gate;

use Symfony\Component\Serializer\Attribute\SerializedName;

class GateSaveRequest
{
    #[SerializedName('timer_offset')]
    public ?int $timerOffset = null;

    #[SerializedName('timer_show')]
    public ?int $timerShow = null;

    #[SerializedName('timer_on_inactive_screen')]
    public bool $timerOnInactiveScreen = false;

    /** @var array<int, GateSaveInfo> */
    public array $gate = [];

    /** @var array<string|int, GateSaveInfo> */
    #[SerializedName('new-gate')]
    public array $newGate = [];

    /** @var int[] */
    #[SerializedName('delete-gate')]
    public array $deleteGate = [];
}
