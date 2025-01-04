<?php

use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use Dibi\Connection;

define("ROOT", dirname(__DIR__).'/');
const INDEX = false;

require_once ROOT."include/load.php";

$db = new Connection([
  'driver' => 'mysqli',
  'host' => 'host.docker.internal',
  'port' => 3306,
  'username' => 'root',
  'charset'=> 'utf8',
  'database' => 'pardubice_lac'
                           ]);

$query = $db->select('*')->from('evo5_games');

foreach ($query->execute() as $row) {
    try {
        // Find game
        $game = Game::query()->where('start = %dt', $row->game_from)->first();
        if ($game !== null) {
            echo 'skipping '.$row->gid.PHP_EOL;
            continue;
        }
        echo 'importing '.$row->gid.PHP_EOL;
        $game = new Game();

        $game->start = $row->game_from;
        $game->end = $row->game_to;
        $game->fileNumber = $row->game_number;
        $game->timing->before = $row->timing_in;
        $game->timing->gameLength = $row->timing_game;
        $game->timing->after = $row->timing_out;
        $game->modeName = $row->style_type;
        $game->gameType = $row->game_type === 1 ? GameModeType::TEAM : GameModeType::SOLO;
        $game->scoring->deathOther = $row->scoring_0;
        $game->scoring->hitOther = $row->scoring_1;
        $game->scoring->deathOwn = $row->scoring_2;
        $game->scoring->hitOwn = $row->scoring_3;

        $game->scoring->shield = $row->scoring_6;
        $game->scoring->agent = $row->scoring_7;
        $game->scoring->machineGun = $row->scoring_8;
        $game->scoring->invisibility = $row->scoring_9;

        $game->importTime = new DateTimeImmutable();

        $teams = $db->select('*')->from('evo5_teams')->where('gid = %i', $row->gid)->fetchAll();
        foreach ($teams as $teamRow) {
            $team = new Team();
            $team->color = $teamRow->team_number;
            $team->name = $teamRow->team_name;
            $team->score = $teamRow->team_score;
            $team->position = $teamRow->team_order;
            $game->addTeam($team);
            $team->setGame($game);
        }
        $players = $db->select('*')->from('evo5_players')->where('gid = %i', $row->gid)->fetchAll();
        $pids = [];
        foreach ($players as $playerRow) {
            $player = new Player();
            $pids[$playerRow->pid] = $player;
            $player->name = substr($playerRow->player_name, 0, 50);
            $player->vest = $playerRow->player_vest;
            $player->score = $playerRow->player_score;
            $player->hits = $playerRow->player_hits;
            $player->hitsOther = $playerRow->player_hits_other;
            $player->hitsOwn = $playerRow->player_hits_own;
            $player->deathsOther = $playerRow->player_death_other;
            $player->deathsOwn = $playerRow->player_death_own;
            $player->deaths = $playerRow->player_death;
            $player->accuracy = $playerRow->player_success;
            $player->ammoRest = $playerRow->player_ammo_rest;
            $player->scoreBonus = $playerRow->player_score_bonus;
            $player->scorePowers = $playerRow->player_score_powers;
            $player->bonus->agent = $playerRow->player_bonus_agent;
            $player->bonus->invisibility = $playerRow->player_bonus_invisibility;
            $player->bonus->shield = $playerRow->player_bonus_shields;
            $player->bonus->machineGun = $playerRow->player_bonus_tommy_gun;
            $player->minesHits = $playerRow->player_mines_hits;
            $player->teamNum = $playerRow->player_team;
            $player->position = $playerRow->player_order;
            $game->players->set($player, $player->vest);
            $player->setGame($game);
        }

        foreach ($game->players->getAll() as $player) {
            // Find team
            foreach ($game->teams->getAll() as $team) {
                if ($player->teamNum === $team->color) {
                    $player->team = $team;
                    break;
                }
            }
        }
        $hits = $db->select('*')->from('evo5_hits')->where('pid IN %in', array_keys($pids))->fetchAssoc('pid|vest');
        foreach ($pids as $pid => $player) {
            foreach ($hits[$pid] ?? [] as $vest => $val) {
                $target = $game->players->get($vest);
                $player->addHits($target, $val->count);
            }
        }
        $game->save();
        echo 'Imported '.$game->start->format('Y-m-d H:i').' - '.$game->code.PHP_EOL;
    } catch (\Exception $e) {
        echo $e->getMessage().PHP_EOL;
    }
}