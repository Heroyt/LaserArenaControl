<?php

namespace App\Tools\GameLoading;

/**
 *
 */
class LasermaxxLoadPlayerData
{
    public function __construct(
      public string  $vest,
      public string  $name,
      public string  $team,
      public bool    $vip = false,
      public ?string $code = null,
      public bool $birthday = false,
    ) {}
}
