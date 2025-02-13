<?php

namespace App\Tools\GameLoading;

use App\Models\System;

interface LoaderInterface
{
    public System $system {
        get;
        set;
    }

    /**
     * Prepare a game for loading
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string,string|numeric> Metadata
     */
    public function loadGame(array $data) : array;

    public function loadMusic(int $musicId, string $musicFile, string $system, ?float $timeSinceStart = null) : void;
}
