<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

class TestCommandHandler implements CommandHandlerInterface
{

    public function handle(CommandInterface $command) : void {
        // OK
    }
}