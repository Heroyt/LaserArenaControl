<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\Info;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Tip;
use App\GameModels\Vest;
use App\Services\EventService;
use Dibi\Exception;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Core\Migrations\MigrationLoader;
use Lsr\Core\Models\Model;
use Lsr\Exceptions\FileException;
use Nette\Utils\AssertionException;
use ReflectionClass;
use ReflectionException;

/**
 * @version 0.3
 */
class DbInstall implements InstallInterface
{

	/** @var array{definition:string, modifications:array<string,string[]>}[] */
	public const TABLES = [
		PrintStyle::TABLE => [
			'definition' => "(
				`id_style` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`name` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
				`color_dark` varchar(7) COLLATE utf8_czech_ci NOT NULL DEFAULT '#304D99',
				`color_light` varchar(7) COLLATE utf8_czech_ci NOT NULL DEFAULT '#a7d0f0',
				`color_primary` varchar(7) COLLATE utf8_czech_ci NOT NULL DEFAULT '#1b4799',
				`bg` varchar(100) COLLATE utf8_czech_ci NOT NULL DEFAULT 'assets/images/print/bg.jpg',
				`bg_landscape` varchar(100) COLLATE utf8_czech_ci NOT NULL DEFAULT 'assets/images/print/bg_landscape.jpg',
				`default` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id_style`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;",
			'modifications' => [
				'0.1' => [
					"ADD `bg_landscape` VARCHAR(100)  NOT NULL  DEFAULT 'assets/images/print/bg_landscape.jpg' AFTER `bg`;",
				],
			],
		],
		PrintStyle::TABLE . '_dates' => [
			'definition' => "(
				`id_style` int(10) unsigned NOT NULL,
				`date_from` date NOT NULL,
				`date_to` date NOT NULL,
				KEY `style` (`id_style`),
				CONSTRAINT `style` FOREIGN KEY (`id_style`) REFERENCES `print_styles` (`id_style`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;",
			'modifications' => [],
		],
		PrintTemplate::TABLE => [
			'definition' => "(
				`id_template` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`slug` varchar(50) NOT NULL DEFAULT '',
				`name` varchar(50) DEFAULT NULL,
				`description` text DEFAULT NULL,
				`orientation` enum('landscape','portrait') NOT NULL DEFAULT 'portrait',
				PRIMARY KEY (`id_template`),
				UNIQUE KEY `slug` (`slug`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		Tip::TABLE => [
			'definition' => "(
				`id_tip` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`text` text DEFAULT NULL,
				PRIMARY KEY (`id_tip`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		EventService::TABLE => [
			'definition' => "(
				`id_event` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`datetime` datetime NOT NULL DEFAULT current_timestamp(),
				`message` text NOT NULL,
				`sent` tinyint(1) NOT NULL DEFAULT 0,
				`sent_dev` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id_event`)
			) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4;",
			'modifications' => [
				'0.1' => [
					'ADD `sent_dev` tinyint(1) NOT NULL DEFAULT 0 AFTER `sent`',
				],
			],
		],
		Vest::TABLE => [
			'definition' => "(
				`id_vest` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`vest_num` int(11) NOT NULL,
				`system` varchar(50) NOT NULL DEFAULT '',
				`grid_col` int(10) unsigned DEFAULT NULL,
				`grid_row` int(10) unsigned DEFAULT NULL,
				PRIMARY KEY (`id_vest`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
	];

	/** @var array<class-string, string> */
	protected static array $classTables = [];

	/**
	 * Install all database tables
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 */
	public static function install(bool $fresh = false): bool {
		// Load migration files
		$loader = new MigrationLoader(ROOT . 'config/migrations.neon');
		try {
			$loader->load();
			$modules = glob(ROOT . 'modules/*/config/migrations.neon');
			foreach ($modules as $module) {
				$loader->migrations = $loader::merge($loader->migrations, $loader->loadFile($module));
			}
		} catch (CyclicDependencyException|FileException|\Nette\Neon\Exception|AssertionException $e) {
			echo "\e[0;31m" . $e->getMessage() . "\e[m\n" . $e->getTraceAsString() . "\n";
			return false;
		}

		/** @var array{definition:string, modifications?:array<string,string[]>}[] $tables */
		$tables = array_merge($loader->migrations, self::TABLES);

		try {
			if ($fresh) {
				// Drop all tables in reverse order
				foreach (array_reverse($tables) as $tableName => $definition) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					DB::getConnection()->query("DROP TABLE IF EXISTS %n;", $tableName);
				}
			}

			// Create tables
			foreach ($tables as $tableName => $info) {
				if (class_exists($tableName)) {
					$tableName = static::getTableNameFromClass($tableName);
					if ($tableName === null) {
						continue;
					}
				}
				$definition = $info['definition'];
				DB::getConnection()->query("CREATE TABLE IF NOT EXISTS %n $definition", $tableName);
			}

			// Game mode view
			DB::getConnection()->query("DROP VIEW IF EXISTS `vModesNames`");
			DB::getConnection()->query("CREATE VIEW IF NOT EXISTS `vModesNames`
AS SELECT
   `a`.`id_mode` AS `id_mode`,
   `a`.`system` AS `system`,
   `a`.`name` AS `name`,
   `a`.`description` AS `description`,
   `a`.`type` AS `type`,
   `b`.`sysName` AS `sysName`
FROM (`game_modes` `a` left join `game_modes-names` `b` on(`a`.`id_mode` = `b`.`id_mode`));");

			// RegressionData view
			DB::getConnection()->query("DROP VIEW IF EXISTS `vEvo5RegressionData`");
			DB::getConnection()->query("CREATE VIEW IF NOT EXISTS `vEvo5RegressionData`
AS SELECT
   `p`.`id_game` AS `id_game`,
   `p`.`hits` AS `hits`,
   `p`.`deaths` AS `deaths`,
   `p`.`hits_other` AS `hits_other`,
   `p`.`deaths_other` AS `deaths_other`,
   `p`.`hits_own` AS `hits_own`,
   `p`.`deaths_own` AS `deaths_own`,
   `p`.`id_team` AS `id_team`,
   `g`.`game_type` AS `game_type`,
   TIMESTAMPDIFF(MINUTE,`g`.`start`, `g`.`end`) AS `game_length`,
   (SELECT COUNT(0) - 1 FROM `evo5_players` `p2` WHERE `p2`.`id_team` = `p`.`id_team`) AS `teammates`,
   (SELECT COUNT(0) from `evo5_players` `p2` WHERE `p2`.`id_team` <> `p`.`id_team` AND `p2`.`id_game` = `p`.`id_game`) AS `enemies`,
   `m`.`id_mode` AS `id_mode`,
   `m`.`rankable` AS `rankable` 
FROM `evo5_players` `p` 
JOIN `evo5_games` `g` ON (`p`.`id_game` = `g`.`id_game`)
JOIN `game_modes` `m` ON (`g`.`id_mode` = `m`.`id_mode` OR `g`.`id_mode` is null AND `m`.`id_mode` = IF(`g`.`game_type` = 'TEAM',1,2))
WHERE `g`.`start` is not null AND `g`.`end` is not null;");

			if (!$fresh) {
				/** @var array<string,string> $tableVersions */
				$tableVersions = (array)Info::get('db_version', []);

				// Update all tables if there have been any changes to the tables
				foreach ($tables as $tableName => $info) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					$currTableVersion = $tableVersions[$tableName] ?? '0.0';
					$maxVersion = $currTableVersion;
					foreach ($info['modifications'] ?? [] as $version => $queries) {
						// Check versions
						if ($version !== 'always') {
							if (version_compare($currTableVersion, $version) > 0) {
								// Skip if this version have already been processed
								continue;
							}
							if (version_compare($maxVersion, $version) < 0) {
								$maxVersion = $version;
							}
						}

						// Run ALTER TABLE queries for current version
						foreach ($queries as $query) {
							echo 'Altering table: ' . $tableName . ' - ' . $query . PHP_EOL;
							try {
								DB::getConnection()->query("ALTER TABLE %n $query;", $tableName);
							} catch (Exception $e) {
								if ($e->getCode() === 1060 || $e->getCode() === 1061) {
									// Duplicate column <-> already created
									continue;
								}
								throw $e;
							}
						}
					}
					$tableVersions[$tableName] = $maxVersion;
				}

				// Update table version cache
				try {
					Info::set('db_version', $tableVersions);
				} catch (Exception) {
				}
			}
		} catch (Exception $e) {
			echo "\e[0;31m" . $e->getMessage() . "\e[m\n" . $e->getSql() . "\n";
			return false;
		}

		return true;
	}

	/**
	 * Get a table name for a Model class
	 *
	 * @param class-string $className
	 *
	 * @return string|null
	 */
	protected static function getTableNameFromClass(string $className): ?string {
		// Check static cache
		if (isset(static::$classTables[$className])) {
			return static::$classTables[$className];
		}

		// Try to get table name from reflection
		try {
			$reflection = new ReflectionClass($className);
		} catch (ReflectionException) { // @phpstan-ignore-line
			// Class not found
			return null;
		}

		// Check if the class is instance of Model
		while ($parent = $reflection->getParentClass()) {
			if ($parent->getName() === Model::class) {
				// Cache result
				static::$classTables[$className] = $className::TABLE;
				return $className::TABLE;
			}
			$reflection = $parent;
		}

		// Class is not a Model
		return null;
	}

}