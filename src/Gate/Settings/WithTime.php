<?php

namespace App\Gate\Settings;

/**
 * Settings contains time settings
 */
trait WithTime
{

    /** @var int|null Maximum time (in seconds) of how long the vests screen should remain active */
    public readonly ?int $time;

    public function getTime() : ?int {
        return $this->time ?? null;
    }

}