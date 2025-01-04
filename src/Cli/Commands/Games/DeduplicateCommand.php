<?php

declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use Dibi\Exception;
use Lsr\Db\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeduplicateCommand extends Command
{
    public static function getDefaultName() : ?string {
        return 'games:deduplicate';
    }

    public static function getDefaultDescription() : ?string {
        return 'Remove duplicate games and players.';
    }

    public function execute(InputInterface $input, OutputInterface $output) : int {
        DB::getConnection()->begin();

        foreach (GameFactory::getSupportedSystems() as $system) {
            $output->writeln(sprintf('Checking games for system <info>%s</info>', $system));

            $games = DB::select(
              $system.'_games',
              'GROUP_CONCAT([id_game]) as [ids], COUNT(*) as [count]'
            )
                       ->groupBy('start')
                       ->having('[count] > 1')
                       ->orderBy('id_game')
                       ->fetchAll(cache: false);

            $removeIds = [];
            foreach ($games as $game) {
                /** @var numeric-string[] $ids */
                $ids = explode(',', $game->ids);
                foreach (array_slice($ids, 0, -1) as $id) {
                    $removeIds[] = (int) $id;
                }
            }

            try {
                $output->writeln(sprintf('Removing %d duplicate games', count($removeIds)));
                DB::delete($system.'_games', ['id_game IN %in', $removeIds]);
            } catch (Exception $e) {
                $output->writeln('<error>'.$e->getMessage().'</error>');
                DB::getConnection()->rollback();
                return self::FAILURE;
            }

            $output->writeln(sprintf('Checking players for system <info>%s</info>', $system));
            $players = DB::select(
              $system.'_players',
              'GROUP_CONCAT([id_player]) as [ids], COUNT(*) as [count]'
            )
                         ->groupBy('id_game, vest')
                         ->having('[count] > 1')
                         ->orderBy('id_player')
                         ->fetchAll(cache: false);

            $removeIds = [];
            foreach ($players as $player) {
                /** @var numeric-string[] $ids */
                $ids = explode(',', $player->ids);
                foreach (array_slice($ids, 0, -1) as $id) {
                    $removeIds[] = (int) $id;
                }
            }

            try {
                $output->writeln(sprintf('Removing %d duplicate players', count($removeIds)));
                DB::delete($system.'_players', ['id_player IN %in', $removeIds]);
            } catch (Exception $e) {
                $output->writeln('<error>'.$e->getMessage().'</error>');
                DB::getConnection()->rollback();
                return self::FAILURE;
            }
        }

        DB::getConnection()->commit();
        return self::SUCCESS;
    }
}
