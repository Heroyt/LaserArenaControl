<?php

namespace App\Tools\GameLoading;

interface LoaderInterface
{
    /**
     * Prepare a game for loading
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string,string|numeric> Metadata
     */
    public function loadGame(array $data): array;

    public function loadMusic(int $musicId, string $musicFile, string $system, ?float $timeSinceStart = null): void;
}
