<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Tools\Evo5\ResultsParser;
use Dibi\Exception;
use JsonException;
use Lsr\Core\ApiController;
use Lsr\Core\App;
use Lsr\Core\CliController;
use Lsr\Core\Constants;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Exceptions\FileException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{

	/** @var array{error?:string,exception?:string,sql?:string}[]|string[] */
	private static array                       $errors = [];
	private static ApiController|CliController $controller;

	private static bool $cliFlag = false;
	private static bool $apiFlag = false;

	/**
	 * Unified import method for CLI or API controller
	 *
	 * Handles result import the same for both, but outputs differently.
	 *
	 * @param string                      $resultsDir Results directory passed from a Controller
	 * @param ApiController|CliController $controller The controller object
	 *
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public static function import(string $resultsDir, ApiController|CliController $controller) : void {
		self::$controller = $controller;
		self::$apiFlag = $controller instanceof ApiController;
		self::$cliFlag = $controller instanceof CliController;

		try {
			$logger = new Logger(LOG_DIR.'results/', 'import');
		} catch (DirectoryCreationException $e) {
			self::errorHandle($e, 500);
			return;
		}

		if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
			self::errorHandle('Results directory does not exist.', 400);
			return;
		}

		$resultsDir = trailingSlashIt($resultsDir);
		/** @var string[] $resultFiles */
		$resultFiles = glob($resultsDir.'*.game');
		/** @var int $lastCheck */
		$lastCheck = Info::get($resultsDir.'check', 0);

		$now = time();

		// Counters
		$imported = 0;
		$total = 0;
		$start = microtime(true);
		/** @var Game|null $lastUnfinishedGame */
		$lastUnfinishedGame = null;
		$lastEvent = '';

		/** @var Game[] $finishedGames */
		$finishedGames = [];

		// Import all files
		foreach ($resultFiles as $file) {
			if (str_ends_with($file, '0000.game')) {
				continue;
			}
			if (filemtime($file) > $lastCheck) {
				$total++;
				if (self::$cliFlag) {
					echo 'Importing: '.$file.PHP_EOL;
				}
				$logger->info('Importing file: '.$file);
				try {
					$parser = new ResultsParser($file, App::getContainer()->getByType(PlayerProvider::class));
					$game = $parser->parse();
					if (!isset($game->importTime)) {
						$logger->debug('Game is not finished');

						// The game is not finished and does not contain any results
						// It is either:
						// - an old, un-played game
						// - freshly loaded game
						// - started and not finished game
						// An old game should be ignored, the other 2 cases should be logged and an event should be sent.
						// But only the latest game should be considered

						// TODO: Detect manually stopped game and delete game-started

						// The game is started
						if ($game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
							$lastUnfinishedGame = $game;
							$lastEvent = 'game-started';
							$logger->debug('Game is started');
							continue;
						}
						// The game is loaded
						if (!$game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp()) <= Constants::GAME_LOADED_TIME) {
							// Check if the last unfinished game is not created later
							if (isset($lastUnfinishedGame) && $game->fileTime < $lastUnfinishedGame->fileTime) {
								continue;
							}
							$lastUnfinishedGame = $game;
							$lastEvent = 'game-loaded';
							$logger->debug('Game is loaded');
						}
						continue;
					}

					// Check players
					$null = true;
					/** @var Player $player */
					foreach ($game->getPlayers() as $player) {
						if ($player->score !== 0 || $player->shots !== 0) {
							$null = false;
							break;
						}
					}
					if ($null) {
						$logger->warning('Game is empty');
						continue; // Empty game - no shots, no hits, etc..
					}

					if (!$game->save()) {
						throw new ResultsParseException('Failed saving game into DB.');
					}

					// Refresh the started-game info to stop the game timer
					/** @var Game $startedGame */
					$startedGame = Info::get($game::SYSTEM.'-game-started');
					/* @phpstan-ignore-next-line */
					if (isset($startedGame) && $game->fileNumber === $startedGame->fileNumber) {
						try {
							Info::set($game::SYSTEM.'-game-started', null);
						} catch (Exception) {
						}
					}

					$gameModel = GameFactory::getById($game->id ?? 0, ['system' => $game::SYSTEM]);
					if (isset($gameModel)) {
						$finishedGames[] = $gameModel;
					}
					$imported++;
				} catch (FileException|GameModeNotFoundException|ResultsParseException|ValidationException $e) {
					$logger->error($e->getMessage());
					$logger->debug($e->getTraceAsString());
					self::errorHandle($e);
				}
			}
		}
		if ($imported > 0) {
			try {
				Info::set($resultsDir.'check', $now);
			} catch (Exception $e) {
				self::errorHandle($e);
			}
		}

		if (isset($lastUnfinishedGame)) {
			/* @phpstan-ignore-next-line */
			$logger->debug('Setting last unfinished game: "'.$lastUnfinishedGame::SYSTEM.'-'.$lastEvent.'" - '.$lastUnfinishedGame->fileNumber);
			try {
				Info::set($lastUnfinishedGame::SYSTEM.'-'.$lastEvent, $lastUnfinishedGame);
				EventService::trigger($lastEvent);
			} catch (Exception $e) {
				self::errorHandle($e);
			}
		}

		// Send event on new import
		if ($imported > 0) {
			EventService::trigger('game-imported');
		}

		// Try to synchronize finished games to public
		if (!empty($finishedGames)) {
			$system = $finishedGames[0]::SYSTEM;
			/** @var LigaApi $liga */
			$liga = App::getService('liga');
			if ($liga->syncGames($system, $finishedGames)) {
				$logger->info('Synchronized games to public.');
				// Set the sync flag
				foreach ($finishedGames as $finishedGame) {
					$finishedGame->sync = true;
					try {
						$finishedGame->save();
					} catch (ValidationException $e) {
						$logger->warning('Failed to synchronize games to public');
						$logger->exception($e);
					}
				}
			}
			else {
				$logger->warning('Failed to synchronize games to public');
			}

			$precacheService = App::getServiceByType(ResultsPrecacheService::class);
			$precacheService->prepareGamePrecache(...array_map(static fn(Game $game) => $game->code, $finishedGames));
		}
		else {
			$logger->info('No games to synchronize to public');
		}

		if (self::$cliFlag) {
			echo 'Successfully imported: '.$imported.'/'.$total.' in '.round(microtime(true) - $start, 2).'s'.PHP_EOL;
			exit(0);
		}
		if (self::$apiFlag) {
			/* @phpstan-ignore-next-line */
			self::$controller->respond(
				[
					'imported' => $imported,
					'total'    => $total,
					'time'     => round(microtime(true) - $start, 2),
					'errors'   => self::$errors,
				]
			);
		}
	}

	/**
	 * Handle an error message according to current controller
	 *
	 * @param string|\Exception $data
	 * @param int               $statusCode
	 *
	 * @return void
	 * @throws JsonException
	 */
	private static function errorHandle(string|\Exception $data, int $statusCode = 0) : void {
		if (self::$cliFlag) {
			$info = '';
			if (is_string($data)) {
				$info = $data;
			}
			else if ($data instanceof \Exception) {
				$info = 'An exception has occurred: '.$data->getMessage();
				if ($data instanceof Exception) {
					$info .= ' - '.$data->getSql();
				}
			}
			/* @phpstan-ignore-next-line */
			self::$controller->errorPrint($info);
			if ($statusCode !== 0) {
				exit($statusCode);
			}
		}
		else if (self::$apiFlag) {
			$info = [];
			if (is_string($data)) {
				$info['error'] = $data;
			}
			else if ($data instanceof \Exception) {
				$info = [
					'error'     => 'An exception has occurred.',
					'exception' => $data->getMessage(),
				];
				if ($data instanceof Exception) {
					$info['sql'] = $data->getSql();
				}
			}
			if ($statusCode === 0) {
				/* @phpstan-ignore-next-line */
				self::$errors[] = $info;
			}
			else {
				/* @phpstan-ignore-next-line */
				self::$controller->respond($info, $statusCode);
			}
		}
	}
}