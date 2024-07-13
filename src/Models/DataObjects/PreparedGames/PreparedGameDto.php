<?php

namespace App\Models\DataObjects\PreparedGames;

use App\Models\DataObjects\NewGame\GameLoadData;
use DateTimeInterface;

class PreparedGameDto
{
    public function __construct(
        public GameLoadData $data,
        public ?int $id = null,
        public ?DateTimeInterface $datetime = null,
        public PreparedGameType $type = PreparedGameType::PREPARED,
        public bool $active = true,
    ) {
    }
}
