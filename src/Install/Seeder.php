<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\Info;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Tip;
use App\GameModels\Vest;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateScreenModel;
use App\Gate\Models\GateType;
use App\Gate\Screens\GeneralDayStatsScreen;
use App\Gate\Screens\Results\ResultsScreen;
use App\Gate\Screens\VestsScreen;
use App\Gate\Settings\ResultsSettings;
use App\Gate\Settings\VestsSettings;
use Dibi\Exception;
use Lsr\Core\DB;

/**
 * Class that initially seeds the database
 */
class Seeder implements InstallInterface
{

	public const GAME_MODES = [
		[
			'id_mode'              => 1,
			'system'               => 'evo5',
			'name'                 => 'Team deathmach',
			'description'          => 'Classic team game.',
			'load_name'            => '1-TEAM-DEATHMACH',
			'type'                 => 'TEAM',
			'public'               => true,
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
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
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
		],
		[
			'id_mode'              => 2,
			'system'               => 'evo5',
			'name'                 => 'Deathmach',
			'description'          => 'Classic free for all game.',
			'load_name'            => '2-SOLO-DEATHMACH',
			'type'                 => 'SOLO',
			'public'               => true,
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
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
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
		],
		[
			'id_mode'              => 3,
			'system'               => 'evo5',
			'name'                 => 'CSGO',
			'description'          => 'Náročná hra o přežití se 3mi životy.',
			'load_name'            => '3-TEAM-CSGO',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => false,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => false,
			'player_favourites'    => false,
			'player_lives'         => true,
			'team_score'           => false,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => false,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 4,
			'system'               => 'evo5',
			'name'                 => 'Základny',
			'description'          => 'Strategická hra, kdy 2 týmy bojují proti sobě o zničení základny druhého týmu.',
			'load_name'            => '3-TEAM-Zakladny',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => false,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => false,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 5,
			'system'               => 'evo5',
			'name'                 => 'Barvičky',
			'description'          => 'Rychlá, šílená hra. Po pár smrtích se přebarvíš na barvu toho, kdo tě trefil.',
			'load_name'            => '3-TEAM-Barvicky',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => false,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => false,
			'team_shots'           => false,
			'team_hits'            => false,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 6,
			'system'               => 'evo5',
			'name'                 => 'T.M.A',
			'description'          => 'Klasická hra, ale tentokrát bez světel.',
			'load_name'            => '3-TEAM-TMA',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => true,
			'part_win'             => false,
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
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 7,
			'system'               => 'evo5',
			'name'                 => 'T.M.A - solo',
			'description'          => 'Klasická hra, ale tentokrát bez světel.',
			'load_name'            => '3-SOLO-TMA',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => true,
			'part_win'             => false,
			'part_teams'           => false,
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
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => false,
			'team_shots'           => false,
			'team_hits'            => false,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 8,
			'system'               => 'evo5',
			'name'                 => 'Apokalypsa',
			'description'          => 'Hra na zombíky! Vybraní hráči jsou zombie, kteří se snaží infikovat ostatní hráče.',
			'load_name'            => '3-TEAM-Apokalypsa',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
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
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 9,
			'system'               => 'evo5',
			'name'                 => 'Survival',
			'description'          => 'Strategická hra s omezeným počtem životů a nábojů.',
			'load_name'            => '3-SOLO-SURVIVAL',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => false,
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
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 10,
			'system'               => 'evo5',
			'name'                 => 'Survival',
			'description'          => 'Strategická hra s omezeným počtem životů a nábojů.',
			'load_name'            => '3-TEAM-SURVIVAL',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
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
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
	];

	public const GAME_MODE_NAMES = [
		[
			'id_mode' => 1,
			'sysName' => '1-TEAM',
		],
		[
			'id_mode' => 2,
			'sysName' => '2-SOLO',
		],
		[
			'id_mode' => 3,
			'sysName' => 'CSGO',
		],
		[
			'id_mode' => 4,
			'sysName' => 'Zakladny',
		],
		[
			'id_mode' => 5,
			'sysName' => 'Barvi',
		],
		[
			'id_mode' => 6,
			'sysName' => 'TEAM-TMA',
		],
		[
			'id_mode' => 7,
			'sysName' => 'SOLO-TMA',
		],
		[
			'id_mode' => 8,
			'sysName' => 'Apokalypsa',
		],
		[
			'id_mode' => 9,
			'sysName' => 'SOLO-Survival',
		],
		[
			'id_mode' => 10,
			'sysName' => 'TEAM-Survival',
		],
	];

	public const PRINT_STYLES = [
		[
			'id_style'      => 1,
			'name'          => 'Default',
			'color_dark'    => '#304D99',
			'color_light'   => '#a7d0f0',
			'color_primary' => '#1b4799',
			'bg'            => 'assets/images/print/bg.jpg',
			'bg_landscape'  => 'assets/images/print/bg_landscape.jpg',
			'default'       => true,
		],
	];

	public const PRINT_TEMPLATES = [
		[
			'id_template' => 1,
			'slug'        => 'default',
			'name'        => 'Default',
			'description' => 'Basic result template',
			'orientation' => 'portrait',
		],
		[
			'id_template' => 2,
			'slug'        => 'graphical',
			'name'        => 'Graphical',
			'description' => 'Graphical template using graphs and other visualisations.',
			'orientation' => 'landscape',
		],
	];

	public const TIPS = [
		1  => 'Ve hře vždy sleduj svoje okolí!',
		2  => 'Při týmové hře není výhodné střílet do hráčů se stejnou barvou.',
		3  => 'Při výstupu z arény vždy připni zbraň k vestě.',
		4  => 'Zabiják vlastního týmu, či největší vlasťnák... To je oč tu běží!',
		5  => 'Ramenní čidla se trefují nejlépe, zádová nejhůř.',
		6  => 'Jak se v aréně střílí, tak se v aréně umírá...',
		7  => 'Čidlo na zbrani, ač nenápadné, se dá velmi dobře trefit.',
		8  => 'Zkušení hráči hrajou týmově!',
		9  => 'Nemá cenu umírat na tom samém místě. Když to nejde, uteč jinam!',
		10 => 'Držení zbraně v obou rukách je přesnější.',
		11 => 'Spoluhráč je nejlepší cíl. Neutíká.',
		12 => 'Nejlepší výherní taktika je strílet do protihráčů.',
		13 => 'Na displeji vesty po zásahu vidíte kdo vás zasáhl do jakého čidla. To pomáhá při orientaci.',
		14 => 'Pohyblivý cíl je vždycky těžší trefit.',
		15 => 'Go, go, go, go...',
		16 => 'Trénink se vyplácí!',
		17 => 'Nezapomeň každý květen a listopad registrovat svůj tým na turnaj!',
		18 => 'Zabití: 100 bodů, Smrt: -50 bodů, Zabití vlastního hráče: -25 bodů',
		19 => 'Střílíme v Písku už od 31.12.2013!!!',
		20 => 'Všechny bonusy, kromě štítu, trvají 30s.',
		21 => 'Dobití protihráče pažbou, vám žádné body nepřidá.',
		22 => 'LASER TAG - SUIT UP!',
		23 => 'Měl bys radši hrát laser game a být úžasný!',
		24 => 'Jen Chuck Norris vás může zastřelit baterkou na svém mobilu.',
		25 => 'Oživení trvá 5 sekund.',
		26 => 'Nikdy nejsi příliš starý na laser game.',
		27 => 'Když tě bolí nohy, kempi!',
		28 => 'Vestu si oblečte vždy zbraní dobředu.',
		29 => 'Pro vyřízení osobních sporů je aréna ideální.',
	];

	public const VESTS = [
		[
			'id_vest'  => 1,
			'vest_num' => 1,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 1,
		],
		[
			'id_vest'  => 2,
			'vest_num' => 2,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 2,
		],
		[
			'id_vest'  => 3,
			'vest_num' => 3,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 3,
		],
		[
			'id_vest'  => 4,
			'vest_num' => 4,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 4,
		],
		[
			'id_vest'  => 5,
			'vest_num' => 5,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 6,
		],
		[
			'id_vest'  => 6,
			'vest_num' => 6,
			'system'   => 'evo5',
			'grid_col' => 1,
			'grid_row' => 6,
		],
		[
			'id_vest'  => 7,
			'vest_num' => 7,
			'system'   => 'evo5',
			'grid_col' => 3,
			'grid_row' => 6,
		],
		[
			'id_vest'  => 8,
			'vest_num' => 8,
			'system'   => 'evo5',
			'grid_col' => 4,
			'grid_row' => 6,
		],
		[
			'id_vest'  => 9,
			'vest_num' => 9,
			'system'   => 'evo5',
			'grid_col' => 5,
			'grid_row' => 6,
		],
		[
			'id_vest'  => 10,
			'vest_num' => 10,
			'system'   => 'evo5',
			'grid_col' => 5,
			'grid_row' => 5,
		],
		[
			'id_vest'  => 11,
			'vest_num' => 11,
			'system'   => 'evo5',
			'grid_col' => 5,
			'grid_row' => 4,
		],
	];

	/**
	 * @inheritDoc
	 */
	public static function install(bool $fresh = false) : bool {
		try {
			// Game modes
			if ($fresh) {
				DB::delete(AbstractMode::TABLE, ['1=1']);
			}
			foreach (self::GAME_MODES as $insert) {
				DB::insertIgnore(AbstractMode::TABLE, $insert);
			}
			if ($fresh) {
				DB::delete(AbstractMode::TABLE.'-names', ['1=1']);
			}
			foreach (self::GAME_MODE_NAMES as $insert) {
				DB::insertIgnore(AbstractMode::TABLE.'-names', $insert);
			}

			// Print styles
			if ($fresh) {
				DB::delete(PrintStyle::TABLE, ['1=1']);
			}
			foreach (self::PRINT_STYLES as $insert) {
				DB::insertIgnore(PrintStyle::TABLE, $insert);
			}

			// Print templates
			if ($fresh) {
				DB::delete(PrintTemplate::TABLE, ['1=1']);
			}
			foreach (self::PRINT_TEMPLATES as $insert) {
				DB::insertIgnore(PrintTemplate::TABLE, $insert);
			}

			// Tips
			if ($fresh) {
				DB::delete(Tip::TABLE, ['1=1']);
			}
			foreach (self::TIPS as $id => $tip) {
				DB::insertIgnore(Tip::TABLE,
				                 [
					                 'id_tip' => $id,
					                 'text'   => $tip,
				                 ]);
			}

			// Vests
			if ($fresh) {
				DB::delete(Vest::TABLE, ['1=1']);
			}
			foreach (self::VESTS as $row) {
				DB::insertIgnore(Vest::TABLE, $row);
			}

			// Info
			Info::set('liga_api_url', 'https://laserliga.cz/');
			Info::set('default_print_template', 'graphical');

			// Default gate
			$count = DB::select(GateType::TABLE, 'COUNT(*)')->fetchSingle(cache: false);
			if ($count === 0) {
				$gate = new GateType();
				$gate->setName('Výchozí')
				     ->setSlug('default')
					->setDescription('Výchozí výsledková tabule.')
					->setLocked(true);

				$idleScreen = new GateScreenModel();
				$idleScreen->screenSerialized = GeneralDayStatsScreen::getDiKey();
				$idleScreen->order = 99;

				$vestsScreen = new GateScreenModel();
				$vestsScreen->order = 10;
				$vestsScreen->trigger = ScreenTriggerType::GAME_LOADED;
				$vestsScreen->setSettings(new VestsSettings());
				$vestsScreen->screenSerialized = VestsScreen::getDiKey();

				$resultsScreen = new GateScreenModel();
				$resultsScreen->order = 10;
				$resultsScreen->trigger = ScreenTriggerType::GAME_ENDED;
				$resultsScreen->setSettings(new ResultsSettings());
				$resultsScreen->screenSerialized = ResultsScreen::getDiKey();

				$gate->addScreenModel($idleScreen, $vestsScreen, $resultsScreen);

				$gate->save();
			}
		} catch (Exception $e) {
			echo $e->getMessage().PHP_EOL.$e->getSql().PHP_EOL;
			return false;
		}
		return true;
	}
}