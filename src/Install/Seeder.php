<?php

namespace App\Install;

use App\Core\DB;
use App\Models\Game\GameModes\AbstractMode;
use App\Models\Game\PrintStyle;
use App\Models\Game\PrintTemplate;

class Seeder implements InstallInterface
{

	/**
	 * @inheritDoc
	 */
	public static function install(bool $fresh = false) : bool {
		// Game modes
		DB::insertIgnore(AbstractMode::TABLE, [
			'id_mode'              => 1,
			'system'               => 'evo5',
			'name'                 => 'Team deathmach',
			'description'          => 'Classic team game.',
			'load_name'            => '1-TEAM-DEATHMACH',
			'type'                 => 'TEAM',
			'public'               => true,
			'win_func'             => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		]);
		DB::insertIgnore(AbstractMode::TABLE, [
			'id_mode'              => 2,
			'system'               => 'evo5',
			'name'                 => 'Deathmach',
			'description'          => 'Classic free for all game.',
			'load_name'            => '2-SOLO-DEATHMACH',
			'type'                 => 'SOLO',
			'public'               => true,
			'win_func'             => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		]);
		DB::insertIgnore(AbstractMode::TABLE.'-names', [
			'id_mode' => 1,
			'sysName' => '1-TEAM',
		]);
		DB::insertIgnore(AbstractMode::TABLE.'-names', [
			'id_mode' => 2,
			'sysName' => '2-SOLO',
		]);

		// Print styles
		DB::insertIgnore(PrintStyle::TABLE, [
			'id_style'      => 1,
			'name'          => 'Default',
			'color_dark'    => '#304D99',
			'color_light'   => '#a7d0f0',
			'color_primary' => '#1b4799',
			'bg'            => 'assets/images/print/bg.jpg',
			'default'       => true,
		]);

		// Print templates
		DB::insertIgnore(PrintTemplate::TABLE, [
			'id_template' => 1,
			'slug'        => 'default',
			'name'        => 'Default',
			'description' => 'Basic result template',
		]);
		return true;
	}
}