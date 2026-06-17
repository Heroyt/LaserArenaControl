<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Info;

class GameStateStorage
{
    public function get(string $key): mixed {
        return Info::get($key);
    }

    /**
     * @throws \Dibi\Exception
     */
    public function set(string $key, mixed $value): void {
        Info::set($key, $value);
    }
}
