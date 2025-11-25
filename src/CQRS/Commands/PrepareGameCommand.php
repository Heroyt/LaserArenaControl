<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\PrepareGameCommandHandler;
use App\DataObjects\PreparedGames\PreparedGameType;
use App\Models\System;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<bool>
 */
final readonly class PrepareGameCommand implements CommandInterface
{

    /**
     * @param PreparedGameType $type
     * @param System|int|string|null $system
     * @param array<string,mixed> $data
     */
    public function __construct(
        public PreparedGameType       $type,
        public System|int|string|null $system,
        public array                  $data,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getHandler(): string
    {
        return PrepareGameCommandHandler::class;
    }
}