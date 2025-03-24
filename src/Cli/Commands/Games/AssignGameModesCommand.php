<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\CQRS\Queries\GameModes\FindModeByNameQuery;
use App\DataObjects\Db\Games\MinimalGameRow;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssignGameModesCommand extends Command
{
    public static function getDefaultName() : ?string {
        return 'games:game-modes';
    }

    public static function getDefaultDescription() : ?string {
        return 'Assign game modes to empty games.';
    }

    protected function configure() : void {
        $this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
        $this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        $limit = (int) $input->getArgument('limit');
        $offset = (int) $input->getArgument('offset');

        $query = GameFactory::queryGames(true)
                            ->where('id_mode IS NULL')
                            ->orderBy('start')
                            ->desc()
                            ->limit($limit)
                            ->offset($offset);


        $games = $query->fetchIteratorDto(MinimalGameRow::class);

        $count = 0;
        foreach ($games as $row) {
            $game = GameFactory::getByCode($row->code);
            if (!isset($game)) {
                continue;
            }
            $query = new FindModeByNameQuery()
              ->consoleName($game->modeName)
              ->type($game->gameType)
              ->systems($game::SYSTEM);
            $mode = $query->get();
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            $game->mode = GameModeFactory::find($game->modeName, $game->gameType, $game::SYSTEM, $output);
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            $output->writeln(
              'Game: '
              .$game->code.' '
              .$game::SYSTEM.' '
              .str_pad($game->modeName, 16).' ('.$game->gameType->value.') '
              .str_pad($game->mode->name ?? 'unknown', 20).' '
              .' DB: '.str_pad(($mode === null ? 'not found' : str_pad((string) $mode->id_mode, 2).' '.$mode->name), 22)
              .' Class: '.str_pad((string) ($game->mode->id ?? 'NULL'), 4).' '
              .$game->mode::class
            );
            if (!$game->save()) {
                $output->writeln('<error>Failed to save game into DB</error>');
            }
            else {
                $count++;
            }
            unset($game);
        }

        $output->writeln('<info>Done - '.$count.' games</info>');
        return self::SUCCESS;
    }

}