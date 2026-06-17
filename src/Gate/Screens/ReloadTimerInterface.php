<?php

declare(strict_types=1);

namespace App\Gate\Screens;

interface ReloadTimerInterface
{
    public function getReloadStartTime(): int;

    public function getReloadTimer(): ?int;
}
