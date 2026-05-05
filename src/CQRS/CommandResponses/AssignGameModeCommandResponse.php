<?php

declare(strict_types=1);

namespace App\CQRS\CommandResponses;

use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use Throwable;

final readonly class AssignGameModeCommandResponse
{
    /**
     * @template G of Game
     * @param  bool  $success
     * @param  ($success is false ? string : null)  $message
     * @param  ($success is false ? Throwable|null : null)  $exception
     * @param  ($success is true ? G : null)  $game
     * @param  ($success is true ? AbstractMode : null)  $mode
     */
    public function __construct(
      public bool          $success = true,
      public ?string       $message = null,
      public ?Throwable    $exception = null,
      public ?Game         $game = null,
      public ?AbstractMode $mode = null,
    ) {}
}
