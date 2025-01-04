<?php

namespace App\Models\DataObjects\PreparedGames;

/**
 * @property string $value
 * @method static PreparedGameType from(string $value)
 * @method static PreparedGameType|null tryFrom(string $value)
 */
enum PreparedGameType : string
{
    case PREPARED   = 'prepared';
    case USER_LOCAL = 'user-local';
    case USER_PUBLIC = 'user-public';
}
