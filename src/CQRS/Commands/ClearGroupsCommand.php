<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\ClearGroupsCommandHandler;
use App\CQRS\CommandResponses\ClearGroupsCommandResponse;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<ClearGroupsCommandResponse>
 */
class ClearGroupsCommand implements CommandInterface
{

    /**
     * @inheritDoc
     */
    public function getHandler() : string {
        return ClearGroupsCommandHandler::class;
    }
}