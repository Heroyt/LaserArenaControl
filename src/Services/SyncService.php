<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use Dibi\Row;
use Lsr\Logging\Logger;
use Throwable;

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
	 * @throws Throwable
	 * @noinspection PhpIllegalArrayKeyTypeInspection
	 */
	public static function syncGames(int $limit = 5, ?float $timeout = null) : void {
		$logger = new Logger(LOG_DIR, 'sync');
		/** @var Row[] $gameRows */
		$gameRows = GameFactory::queryGames(true)->where('[sync] = 0')->limit($limit)->orderBy('start')->desc()->fetchAll(cache: false);

		if (empty($gameRows)) {
			if (PHP_SAPI === 'cli') {
				echo 'No games to synchronize.'.PHP_EOL;
			}
			$logger->info('No games to synchronize.');
			return;
		}

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
			$game = GameFactory::getByCode($row->code);
			if (isset($game)) {
				$systems[$row->system][] = $game;
			}
		}

		// Time it
		$start = microtime(true);
		$systemTimes = [];
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
		}
	}

}