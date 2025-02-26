<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\TestCommandHandler;
use Lsr\CQRS\CommandInterface;

class TestCommand implements CommandInterface
{

    public function getHandler() : string {
        return TestCommandHandler::class;
    }
}