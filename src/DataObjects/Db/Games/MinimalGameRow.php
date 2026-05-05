<?php

declare(strict_types=1);

namespace App\DataObjects\Db\Games;

use DateTimeInterface;

class MinimalGameRow
{
    public int $id_game;
    public ?int $id_mode;
    public int $id_arena;
    public string $system;
    public string $code;
    public DateTimeInterface $start;
    public ?DateTimeInterface $end = null;
}
