<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Games;

use DateTimeInterface;

class ListRequest
{
    public ?DateTimeInterface $date = null;
    public bool $expand = false;
    public bool $excludeFinished = false;
    public ?int $limit = null;
    public ?int $offset = null;
    public ?string $system = null;
    public ?string $orderBy = null;
    public bool $desc = false;
}
