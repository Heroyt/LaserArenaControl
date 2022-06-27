<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Services;

use App\Exceptions\ValidationException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Dibi\DateTime;
use Lsr\Logging\Logger;

/**
 * Service class for synchronization functions with public API
 */
class SyncService
{

	/**
	 * Synchronize not synchronized games to public
	 *
	 * @param int        $limit   Maximum number of games to sync
	 * @param float|null $timeout Timeout for each request in seconds
	 *
	 * @return void
	 */
	public static function syncGames(int $limit = 5, ?float $timeout = null) : void {
		$logger = new Logger(LOG_DIR, 'sync');
		/** @var object{id_game:int,system:string,code:string,start:DateTime,end:DateTime,sync:int}[] $gameRows */
		$gameRows = GameFactory::queryGames(true)->where('[sync] = 0')->limit($limit)->fetchAll();

		if (empty($gameRows)) {
			if (PHP_SAPI === 'cli') {
				echo 'No games to synchronize.'.PHP_EOL;
			}
			$logger->info('No games to synchronize.');
			return;
		}

		//$recreateClient = count($gameRows) > 10;

		$message = 'Starting sync for games: '.implode(', ', array_map(static function(object $row) {
				return $row->id_game.' - '.$row->code;
			}, $gameRows));
		$logger->info($message);
		if (PHP_SAPI === 'cli') {
			echo $message.PHP_EOL;
		}

		// Split games by their system
		$systems = [];
		foreach ($gameRows as $row) {
			if (!isset($systems[$row->system])) {
				$systems[$row->system] = [];
			}
			$systems[$row->system][] = GameFactory::getByCode($row->code);
		}

		/** @var LigaApi $liga */
		//$liga = App::getService('liga');

		// Time it
		$start = microtime(true);
		$systemTimes = [];
		//$apiTime = 0.0;
		//$dbTime = 0.0;
		// Sync each system individually
		foreach ($systems as $system => $games) {
			$systemStart = microtime(true);
			$logger->info('Synchronizing "'.$system.'" system. ('.count($games).' games)');
			$systemTimes[$system] = 0.0;
			// Send request in batches of 2 games max
			//$batchNum = 1;
			foreach ($games as $key => $game) {
				if (!$game->sync()) {
					$logger->warning('Failed to synchronize "'.$system.'" system (game '.$key.')');
				}
			}
			/*do {
				$batch = array_splice($games, 0, 2);
				$apiStart = microtime(true);
				if ($liga->syncGames($system, $batch, $timeout, $recreateClient)) {
					$apiTime += microtime(true) - $apiStart;
					$dbStart = microtime(true);
					self::setSyncFlag($batch);
					$dbTime += microtime(true) - $dbStart;
				}
				else {
					$apiTime += microtime(true) - $apiStart;
					$logger->warning('Failed to synchronize "'.$system.'" system (batch '.$batchNum.')');
				}
				$batchNum++;
			} while (!empty($games));*/
			$systemTimes[$system] += microtime(true) - $systemStart;
		}
		$message = 'Synchronization end. Times: ';
		foreach ($systemTimes as $system => $time) {
			$message .= $system.': '.$time.'s (avg: '.($time / count($systems[$system])).'s), ';
		}
		$message .= 'total: '.(microtime(true) - $start).'s.';
		$logger->info($message);
		if (PHP_SAPI === 'cli') {
			echo $message.PHP_EOL;
			//echo 'API time: '.$apiTime.'s'.PHP_EOL;
			//echo 'DB time: '.$dbTime.'s'.PHP_EOL;
		}
	}

	/**
	 * @param Game[] $games
	 *
	 * @return void
	 */
	private static function setSyncFlag(array $games) : void {
		foreach ($games as $game) {
			$game->sync = true;
			try {
				$game->save();
			} catch (ValidationException $e) {
			}
		}
	}

}