<?php

declare(strict_types=1);

namespace App\CQRS\CommandResponses;

final class ClearGroupsCommandResponse
{
    public function __construct(
      public int $deleted = 0,
      public int $hidden = 0,
    ) {}
}
