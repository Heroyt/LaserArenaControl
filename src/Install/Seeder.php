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
use App\Gate\Screens\DayStats\GeneralStatsScreen;
use App\Gate\Screens\DayStats\HighlightsScreen;
use App\Gate\Screens\MonthStats\TopPlayersScreen;
use App\Gate\Screens\MusicModesScreen;
use App\Gate\Screens\Results\ResultsScreen;
use App\Gate\Screens\TimerScreen;
use App\Gate\Screens\VestGunAfterGameScreen;
use App\Gate\Screens\VestsScreen;
use App\Gate\Settings\MusicModeSettings;
use App\Gate\Settings\ResultsSettings;
use App\Gate\Settings\TimerSettings;
use App\Gate\Settings\VestGunAfterGameSettings;
use App\Gate\Settings\VestsSettings;
use App\Models\System;
use Dibi\Exception;
use Lsr\Db\DB;

/**
 * Class that initially seeds the database
 */
class Seeder implements InstallInterface
{
    public const array GAME_MODES = [
      [
        'id_mode'              => 1,
        'systems' => 'evo5,evo6',
        'name'                 => 'Team Deathmatch',
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
        'systems' => 'evo5,evo6',
        'name'                 => 'Deathmatch',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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
        'systems' => 'evo5,evo6',
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

    public const array GAME_MODE_NAMES = [
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

    public const array PRINT_STYLES = [
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

    public const array PRINT_TEMPLATES = [
      [
        'id_template' => 1,
        'slug'        => 'default',
        'name'        => 'Tabulkové',
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

    public const array TIPS = [
      1  => [
        'text' => 'Ve hře vždy sleduj svoje okolí!',
        'translations' => [
          'de_DE' => 'Sei immer auf deine Umgebung aufmerksam!',
          'en_US' => 'Always be aware of your surroundings!',
          'es_ES' => '¡Siempre vigila tus alrededores durante el partido!',
          'fr_FR' => 'Soyez toujours conscient de ce qui vous entoure',
          'sk_SK' => 'V hre vždy sleduj svoje okolie!',
        ],
      ],
      2  => [
        'text' => 'Při týmové hře není výhodné střílet do hráčů se stejnou barvou.',
        'translations' => [
          'de_DE' => 'Es ist nicht vorteilhaft, auf Spieler mit der gleichen Farbe während eines Teamspiels zu schießen.',
          'en_US' => 'It is not very beneficial to shoot the players with the same colour during a team deathmatch.',
          'es_ES' => 'No es muy beneficioso disparar a los jugadores con el mismo color durante una partida por equipos.',
          'fr_FR' => 'Dans un jeu d\'équipe, il n\'est pas avantageux de tirer sur des joueurs de la même couleur.',
          'sk_SK' => 'Pri tímovej hre nie je výhodné strieľať do hráčov s rovnakou farbou.',
        ],
      ],
      3  => [
        'text' => 'Při výstupu z arény vždy připni zbraň k vestě.',
        'translations' => [
          'de_DE' => 'Beim Verlassen der Arena immer die Waffe an die Weste anschließen.',
          'en_US' => 'When coming out from the arena, always plug your gun on the vest.',
          'es_ES' => 'Al salir de la arena, siempre conecta tu arma al chaleco.',
          'fr_FR' => 'En sortant de l\'arène, attache toujours ton arme à ton gilet.',
          'sk_SK' => 'Pri výstupe z arény vždy pripni zbraň k veste.',
        ],
      ],
      4  => [
        'text' => 'Zabiják vlastního týmu, či největší vlasťnák... To je oč tu běží!',
        'translations' => [
          'de_DE' => 'Teamkiller oder der größte Eigenbeschuss... Das ist hier die Frage!',
          'en_US' => 'Hit or be hit; there is no other way!',
          'es_ES' => 'Asesino de tu propio equipo o el mayor fallo propio... ¡Eso es lo que está en juego!',
          'fr_FR' => 'Touchez ou soyez touchés, il n\'y a pas autre chose !',
          'sk_SK' => 'Zabijak vlastného tímu, či najväčší vlastnák... To je o čo tu beží!',
        ],
      ],
      5  => [
        'text' => 'Ramenní čidla se trefují nejlépe, zádová nejhůř.',
        'translations' => [
          'de_DE' => 'Schultern sind am leichtesten zu treffen; der Rücken am schwersten.',
          'en_US' => 'Shoulders are the easiest to hit; back is the hardest.',
          'es_ES' => 'Los hombros son los más fáciles de acertar; la espalda es la más difícil.',
          'fr_FR' => 'Les épaules sont les plus faciles à toucher; le dos est le plus difficile.',
          'sk_SK' => 'Ramenné čidlá sa triafajú najlepšie, chrbtové najhoršie.',
        ],
      ],
      6  => [
        'text' => 'Jak se v aréně střílí, tak se v aréně umírá...',
        'translations' => [
          'de_DE' => 'Wie man in der Arena schießt, so stirbt man auch...',
          'en_US' => 'A game a day keeps the improvements coming your way…',
          'es_ES' => 'Como disparas en la arena, así es como mueres...',
          'fr_FR' => 'Comme tu tires dans l\'arène, ainsi tu meurs...',
          'sk_SK' => 'Ako sa v aréne strieľa, tak sa v aréne umiera...',
        ],
      ],
      7  => [
        'text' => 'Čidlo na zbrani, ač nenápadné, se dá velmi dobře trefit.',
        'translations' => [
          'de_DE' => 'Der Waffensensor ist recht einfach zu treffen.',
          'en_US' => 'The gun sensor is quite easy to hit.',
          'es_ES' => 'El sensor en el arma, aunque discreto, puede ser muy fácil de acertar.',
          'fr_FR' => 'La cible sur le pistolet est un peu invisible, pourtant facile a touché.',
          'sk_SK' => 'Čidlo na zbrani, hoci nenápadné, sa dá veľmi dobre trafiť.',
        ],
      ],
      8  => [
        'text' => 'Zkušení hráči hrajou týmově!',
        'translations' => [
          'de_DE' => 'Erfahrene Spieler spielen im Team!',
          'en_US' => 'Pro players cooperate with their teams!',
          'es_ES' => '¡Los jugadores con experiencia juegan en equipo!',
          'fr_FR' => 'Les joueurs experimentés coopèrent avec leurs équipes!',
          'sk_SK' => 'Skúsení hráči hrajú tímovo!',
        ],
      ],
      9  => [
        'text' => 'Nemá cenu umírat na tom samém místě. Když to nejde, uteč jinam!',
        'translations' => [
          'de_DE' => 'Es hat keinen Sinn, am gleichen Ort zu sterben. Wenn es nicht geht, lauf woanders hin!',
          'en_US' => 'There is no use dying in the same place. If it’s hard, run somewhere else!',
          'es_ES' => 'No tiene sentido morir en el mismo lugar. Si es difícil, corre a otro sitio.',
          'fr_FR' => 'Il ne sert à rien de mourir au même endroit. Si c\'est difficile, cours ailleurs !',
          'sk_SK' => 'Nemá cenu umierať na tom istom mieste. Keď to nejde, uteč inam!',
        ],
      ],
      10 => [
        'text' => 'Držení zbraně v obou rukách je přesnější.',
        'translations' => [
          'de_DE' => 'Das Halten der Waffe mit beiden Händen ist genauer.',
          'en_US' => 'Holding a gun with both hands is more accurate.',
          'es_ES' => 'Sujetar un arma con ambas manos es más preciso.',
          'fr_FR' => 'Tenir une arme avec les deux mains est plus précis.',
          'sk_SK' => 'Držanie zbrane v oboch rukách je presnejšie.',
        ],
      ],
      11 => [
        'text' => 'Spoluhráč je nejlepší cíl. Neutíká.',
        'translations' => [
          'de_DE' => 'Ein Teamkollege ist das beste Ziel. Er läuft nicht weg.',
          'en_US' => 'Teammates are the best targets. They never run away.',
          'es_ES' => 'Los compañeros de equipo son los mejores objetivos. Nunca huyen.',
          'fr_FR' => 'Un coéquipier est la meilleure cible. Il ne fuit pas.',
          'sk_SK' => 'Spoluhráč je najlepší cieľ. Neuteká.',
        ],
      ],
      12 => [
        'text' => 'Nejlepší výherní taktika je strílet do protihráčů.',
        'translations' => [
          'de_DE' => 'Die beste Siegertaktik ist, auf deine Feinde zu schießen.',
          'en_US' => 'The best winning tactic is to shoot your enemies.',
          'es_ES' => 'La mejor táctica para ganar es disparar a tus enemigos.',
          'fr_FR' => 'La tactique gagnante est de tirer sur vos ennemis.',
          'sk_SK' => 'Najlepšia výherná taktika je strieľať do protihráčov.',
        ],
      ],
      13 => [
        'text' => 'Na displeji vesty po zásahu vidíte kdo vás zasáhl do jakého čidla. To pomáhá při orientaci.',
        'translations' => [
          'de_DE' => 'Auf dem Weste-Display siehst du, wer dich getroffen hat und welchen Sensor.',
          'en_US' => 'You can see who hit you and which sensor on your vest’s display.',
          'es_ES' => 'En la pantalla del chaleco puedes ver quién te golpeó y qué sensor alcanzó.',
          'fr_FR' => 'Sur l\'écran de votre gilet, vous voyez qui vous a touché et quel capteur.',
          'sk_SK' => 'Na displeji vesty po zásahu vidíte kto vás zasiahol do akého čidla. To pomáha pri orientácii.',
        ],
      ],
      14 => [
        'text' => 'Pohyblivý cíl je vždycky těžší trefit.',
        'translations' => [
          'de_DE' => 'Es ist immer schwieriger, ein sich bewegendes Ziel zu treffen.',
          'en_US' => 'It\'s always more difficult to hit a moving target.',
          'es_ES' => 'Siempre es más difícil acertar a un objetivo en movimiento.',
          'fr_FR' => 'Il est toujours plus difficile de toucher une cible en mouvement.',
          'sk_SK' => 'Pohyblivý cieľ je vždy ťažšie trafiť.',
        ],
      ],
      15 => [
        'text' => 'Go, go, go, go...',
        'translations' => [
          'de_DE' => 'Go, go, go, go...',
          'en_US' => 'Go, go, go, go...',
          'es_ES' => 'Go, go, go, go...',
          'fr_FR' => 'Go, go, go, go...',
          'sk_SK' => 'Go, go, go, go...',
        ],
      ],
      16 => [
        'text' => 'Trénink se vyplácí!',
        'translations' => [
          'de_DE' => 'Training zahlt sich aus!',
          'en_US' => 'Training pays off!',
          'es_ES' => '¡El entrenamiento vale la pena!',
          'fr_FR' => 'Entraînement porte ses fruits!',
          'sk_SK' => 'Tréning sa vypláca!',
        ],
      ],
      18 => [
        'text' => 'Zabití: 100 bodů, Smrt: -50 bodů, Zabití vlastního hráče: -25 bodů',
        'translations' => [
          'de_DE' => 'Treffer: 100 Punkte, Tod: -50 Punkte, Teamkollege getroffen: -25 Punkte.',
          'en_US' => 'Hit: 100 points, Death: -50 points, Teammate hit: -25 points',
          'es_ES' => 'Golpe: 100 puntos, Muerte: -50 puntos, Golpe a un compañero de equipo: -25 puntos',
          'fr_FR' => 'Touche: 100 points, Mort: -50 points, Touche réussie sur un coéquipier: -25 points',
          'sk_SK' => 'Zabitie: 100 bodov, Smrť: -50 bodov, Zabitie vlastného hráča: -25 bodov',
        ],
      ],
      20 => [
        'text' => 'Všechny bonusy, kromě štítu, trvají 30s.',
        'translations' => [
          'de_DE' => 'Alle Boni, außer dem Schild, dauern 30 Sekunden.',
          'en_US' => 'All powers, apart from the shield, last for the 30s.',
          'es_ES' => 'Todos los poderes, excepto el escudo, duran 30 segundos.',
          'fr_FR' => 'Tous les bonus, sauf le bouclier, durent 30 secondes.',
          'sk_SK' => 'Všetky bonusy, okrem štítu, trvajú 30s.',
        ],
      ],
      21 => [
        'text' => 'Dobití protihráče pažbou, vám žádné body nepřidá.',
        'translations' => [
          'de_DE' => 'Nahkampftreffer geben keine Punkte.',
          'en_US' => 'Melee hits do not add any score.',
          'es_ES' => 'Golpear a un oponente con la culata no te sumará ningún punto.',
          'fr_FR' => 'Les coups de corps à corps ne rapportent aucun point.',
          'sk_SK' => 'Dobitie protihráča pažbou, vám žiadne body nepridá.',
        ],
      ],
      24 => [
        'text' => 'Jen Chuck Norris vás může zastřelit baterkou na svém mobilu.',
        'translations' => [
          'de_DE' => 'Nur Chuck Norris kann dich mit der Taschenlampe seines Handys treffen.',
          'en_US' => 'Only Chuck Norris can hit you with his mobile’s flashlight.',
          'es_ES' => 'Solo Chuck Norris puede dispararte con la linterna de su móvil.',
          'fr_FR' => 'Seul Chuck Norris peut vous toucher avec la lampe torche de son téléphone.',
          'sk_SK' => 'Len Chuck Norris vás môže zastreliť baterkou na svojom mobile.',
        ],
      ],
      25 => [
        'text' => 'Oživení trvá 5 sekund.',
        'translations' => [
          'de_DE' => 'Wiederbeleben dauert 5 Sekunden.',
          'en_US' => 'Respawn is 5 seconds.',
          'es_ES' => 'El respawn dura 5 segundos.',
          'fr_FR' => 'La réactivation dure 5 secondes.',
          'sk_SK' => 'Oživenie trvá 5 sekúnd.',
        ],
      ],
      26 => [
        'text' => 'Nikdy nejsi příliš starý na laser game.',
        'translations' => [
          'de_DE' => 'Du bist nie zu alt für Laser-Tag.',
          'en_US' => 'You are never too old to play laser game.',
          'es_ES' => 'Nunca eres demasiado viejo para jugar al laser tag.',
          'fr_FR' => 'Vous n\'êtes jamais trop vieux pour un jeu laser.',
          'sk_SK' => 'Nikdy nie si príliš starý na laser game.',
        ],
      ],
      27 => [
        'text' => 'Když tě bolí nohy, kempi!',
        'translations' => [
          'de_DE' => 'Wenn deine Beine schmerzen, versuche zu campen!',
          'en_US' => 'If your legs hurt, try camping!',
          'es_ES' => 'Si te duelen las piernas, prueba a acampar.',
          'fr_FR' => 'Si vous avez mal aux jambes, essayez de trouver un coin idéal pour le tir!',
          'sk_SK' => 'Keď ťa bolí nohy, kempy!',
        ],
      ],
      28 => [
        'text' => 'Vestu si oblečte vždy zbraní dobředu.',
        'translations' => [
          'de_DE' => 'Zieh deine Weste immer mit der Waffe nach vorne an.',
          'en_US' => 'Always put your vest on with your gun in the front.',
          'es_ES' => 'Ponte el chaleco siempre con el arma hacia adelante.',
          'fr_FR' => 'Portez toujours le gilet avec le pistolet à l\'avant.',
          'sk_SK' => 'Vestu si oblečte vždy zbraňou dobredu.',
        ],
      ],
      29 => [
        'text' => 'Pro vyřízení osobních sporů je aréna ideální.',
        'translations' => [
          'de_DE' => 'Die Arena ist der beste Ort, um persönliche Streitigkeiten zu klären.',
          'en_US' => 'The arena is the best place to settle personal disputes.',
          'es_ES' => 'Para resolver conflictos personales, la arena es ideal.',
          'fr_FR' => 'L\'arène est le meilleur endroit pour régler les différends personnels.',
          'sk_SK' => 'Na vybavenie osobných sporov je aréna ideálna.',
        ],
      ],
    ];

    public const array VESTS = [];

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
                DB::insertIgnore(
                  Tip::TABLE,
                  [
                    'id_tip' => $id,
                    'text'   => $tip['text'],
                    'translations' => igbinary_serialize($tip['translations']),
                  ]
                );
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
            $defaultGate = GateType::getBySlug('default');
            if (!isset($defaultGate)) {
                $defaultGate = new GateType();
                $defaultGate->setName('Výchozí')
                            ->setSlug('default')
                            ->setDescription('Výchozí výsledková tabule.')
                            ->setLocked(true);
            }

            $idleScreens = $defaultGate->getScreensForTrigger(ScreenTriggerType::DEFAULT);
            $idleScreen = $idleScreens[0] ?? new GateScreenModel();
            if (!isset($idleScreens[0])) {
                $defaultGate->addScreenModel($idleScreen);
            }
            $idleScreen->screenSerialized = TimerScreen::getDiKey();
            $idleScreen->order = 99;

            $child1 = new GateScreenModel();
            $child1->screenSerialized = GeneralStatsScreen::getDiKey();

            $child2 = new GateScreenModel();
            $child2->screenSerialized = HighlightsScreen::getDiKey();

            $child3 = new GateScreenModel();
            $child3->screenSerialized = TopPlayersScreen::getDiKey();


            $idleScreen->setSettings(
              new TimerSettings(
                [$child1, $child2, $child3],
                60
              )
            );

            $vestsScreens = $defaultGate->getScreensForTrigger(ScreenTriggerType::GAME_LOADED);
            $vestsScreen = $vestsScreens[0] ?? new GateScreenModel();
            if (!isset($vestsScreens[0])) {
                $defaultGate->addScreenModel($vestsScreen);
            }
            $vestsScreen->order = 10;
            $vestsScreen->trigger = ScreenTriggerType::GAME_LOADED;
            $vestsScreen->setSettings(new VestsSettings());
            $vestsScreen->screenSerialized = VestsScreen::getDiKey();

            $resultsScreens = $defaultGate->getScreensForTrigger(ScreenTriggerType::GAME_ENDED);

            $vestGunScreen = $resultsScreens[0] ?? new GateScreenModel();
            if (!isset($resultsScreens[0])) {
                $defaultGate->addScreenModel($vestGunScreen);
            }
            $vestGunScreen->order = 5;
            $vestGunScreen->trigger = ScreenTriggerType::GAME_ENDED;
            $vestGunScreen->setSettings(new VestGunAfterGameSettings(20));
            $vestGunScreen->screenSerialized = VestGunAfterGameScreen::getDiKey();

            $resultsScreen = $resultsScreens[1] ?? new GateScreenModel();
            if (!isset($resultsScreens[1])) {
                $defaultGate->addScreenModel($resultsScreen);
            }
            $resultsScreen->order = 10;
            $resultsScreen->trigger = ScreenTriggerType::GAME_ENDED;
            $resultsScreen->setSettings(new ResultsSettings(120));
            $resultsScreen->screenSerialized = ResultsScreen::getDiKey();

            $manualScreens = $defaultGate->getScreensForTrigger(ScreenTriggerType::RESULTS_MANUAL);
            $manualScreen = $manualScreens[0] ?? new GateScreenModel();
            if (!isset($manualScreens[0])) {
                $defaultGate->addScreenModel($manualScreen);
            }
            $manualScreen->order = 1;
            $manualScreen->trigger = ScreenTriggerType::RESULTS_MANUAL;
            $manualScreen->setSettings(new ResultsSettings());
            $manualScreen->screenSerialized = ResultsScreen::getDiKey();

            $musicScreens = $defaultGate->getScreensForTrigger(ScreenTriggerType::CUSTOM);
            $musicScreen = $musicScreens[0] ?? new GateScreenModel();
            if (!isset($musicScreens[0])) {
                $defaultGate->addScreenModel($musicScreen);
            }
            $musicScreen->order = 0;
            $musicScreen->trigger = ScreenTriggerType::CUSTOM;
            $musicScreen->triggerValue = lang('Hudba');
            $musicScreen->setSettings(new MusicModeSettings());
            $musicScreen->screenSerialized = MusicModesScreen::getDiKey();

            // SYSTEMS
            if (!System::exists(1)) {
                DB::insertIgnore(
                  System::TABLE,
                  [
                    'id_system' => 1,
                    'name'      => 'Evo5',
                    'type'      => 'evo5',
                    'default'   => true,
                    'active'    => true,
                  ]
                );
                DB::update(Vest::TABLE, ['id_system' => 1], ['id_system IS NULL']);
            }


            if (!$defaultGate->save()) {
                echo 'Failed to save default gate.'.PHP_EOL;
            }
        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL.$e->getSql().PHP_EOL;
            return false;
        }
        return true;
    }
}
