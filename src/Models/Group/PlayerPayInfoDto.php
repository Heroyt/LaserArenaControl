<?php

namespace App\Models\Group;

use JsonSerializable;

/**
 * DTO to store information about game group's players' payment info (if they already paid for their games)
 */
class PlayerPayInfoDto implements JsonSerializable
{
    public function __construct(
        public string $playerName,
        public int    $gamesPlayed,
        public int    $gamesPaid = 0,
        public ?int   $priceGroupId = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
