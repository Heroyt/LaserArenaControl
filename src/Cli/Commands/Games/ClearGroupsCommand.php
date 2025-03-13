<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ClearGroupsCommand extends Command
{

    public function __construct(
      private readonly CommandBus $commandBus
    ) {
        parent::__construct();
    }

    public static function getDefaultName() : string {
        return 'games:clear-groups';
    }

    public static function getDefaultDescription() : string {
        return 'Clear old game groups.';
    }

    public function run(InputInterface $input, OutputInterface $output) : int {
        $result = $this->commandBus->dispatch(new \App\CQRS\Commands\ClearGroupsCommand());
        $output->writeln("<info>Cleared old game groups</info>");
        $output->writeln(sprintf('%d deleted', $result->deleted));
        $output->writeln(sprintf('%d hidden', $result->hidden));

        return self::SUCCESS;
    }

}