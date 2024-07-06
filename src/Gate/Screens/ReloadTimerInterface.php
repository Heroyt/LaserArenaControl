<?php

namespace App\Gate\Screens;

interface ReloadTimerInterface
{
    public function getReloadStartTime(): int;

    public function getReloadTimer(): ?int;
}
