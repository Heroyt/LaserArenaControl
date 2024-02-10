<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Tools\Interfaces\ResultsParserInterface;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Controllers\CliController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{

	/** @var array{error?:string,exception?:string,sql?:string}[]|string[] */
	private array                       $errors = [];
	private ApiController|CliController $controller;

	private bool $cliFlag = false;
	private bool $apiFlag = false;
	private int $gameLoadedTime;
	private int $gameStartedTime;

	public function __construct(private readonly EventService $eventService, Config $config) {
		$this->gameLoadedTime = (int)($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
		$this->gameStartedTime = (int)($config->getConfig('ENV')['GAME_STARTED_TIME'] ?? 1800);
	}

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
	public function import(string $resultsDir, ApiController|CliController $controller): void {
		$this->controller = $controller;
		$this->apiFlag = $controller instanceof ApiController;
		$this->cliFlag = $controller instanceof CliController;

		try {
			$logger = new Logger(LOG_DIR . 'results/', 'import');
		} catch (DirectoryCreationException $e) {
			$this->errorHandle($e, 500);
			return;
		}

		if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
			$this->errorHandle('Results directory does not exist.', 400);
			return;
		}

		$playerProvider = App::getContainer()->getByType(PlayerProvider::class);
		$now = time();

		$errors = [];
		$processedFiles = [];

		// Counters
		$imported = 0;
		$total = 0;
		$start = microtime(true);
		/** @var Game|null $lastUnfinishedGame */
		$lastUnfinishedGame = null;
		$lastEvent = '';

		/** @var Game[] $finishedGames */
		$finishedGames = [];

		$resultsDir = trailingSlashIt($resultsDir);
		/** @var int $lastCheck */
		$lastCheck = Info::get($resultsDir . 'check', 0);
		$baseNamespace = 'App\\Tools\\ResultParsing\\';
		foreach (GameFactory::getSupportedSystems() as $system) {
			/** @var class-string<ResultsParserInterface<Game>> $class */
			$class = $baseNamespace . ucfirst($system) . 'ResultsParser';
			if (!class_exists($class)) {
				continue;
			}
			/** @var string[] $resultFiles */
			$resultFiles = glob($resultsDir . $class::getFileGlob());

			// Import all files
			foreach ($resultFiles as $file) {
				// Skip duplicate and invalid files
				if (isset($processedFiles[$file]) || str_ends_with($file, '0000.game') || $class::checkFile($file)) {
					continue;
				}
				$processedFiles[$file] = true;
				if (filemtime($file) > $lastCheck) {
					$total++;
					if ($this->cliFlag) {
						echo 'Importing: ' . $file . PHP_EOL;
					}
					$logger->info('Importing file: ' . $file);
					try {
						$parser = new $class($file, $playerProvider);
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
							if (
								$game->started &&
								isset($game->fileTime) &&
								($now - $game->fileTime->getTimestamp()) <= $this->gameStartedTime
							) {
								$lastUnfinishedGame = $game;
								$lastEvent = 'game-started';
								$logger->debug('Game is started');
								continue;
							}
							// The game is loaded
							if (
								!$game->started &&
								isset($game->fileTime) &&
								($now - $game->fileTime->getTimestamp()) <= $this->gameLoadedTime
							) {
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
						/** @var Game|null $startedGame */
						$startedGame = Info::get($system . '-game-started');
						if (isset($startedGame) && $game->resultsFile === $startedGame->resultsFile) {
							try {
								Info::set($system . '-game-started', null);
							} catch (Exception) {
							}
						}

						$gameModel = GameFactory::getById($game->id ?? 0, ['system' => $system]);
						if (isset($gameModel)) {
							$finishedGames[] = $gameModel;
						}
						$imported++;
					} catch (Throwable $e) {
						$logger->error($e->getMessage());
						$logger->debug($e->getTraceAsString());
						$errors[] = $e;
					}
				}
			}

		}
		if ($imported > 0) {
			try {
				Info::set($resultsDir . 'check', $now);
			} catch (Exception $e) {
				$this->errorHandle($e);
			}
		}

		if (isset($lastUnfinishedGame)) {
			$logger->debug(
				'Setting last unfinished game: "' . $lastUnfinishedGame::SYSTEM . '-' . $lastEvent . '" - ' . $lastUnfinishedGame->resultsFile
			);
			try {
				Info::set($lastUnfinishedGame::SYSTEM . '-' . $lastEvent, $lastUnfinishedGame);
				$this->eventService->trigger($lastEvent, ['game' => $lastUnfinishedGame->resultsFile]);
			} catch (Exception $e) {
				$this->errorHandle($e);
			}
		}

		// Send event on new import
		if ($imported > 0) {
			$this->eventService->trigger('game-imported', ['count' => $imported]);
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

			/** @var ResultsPrecacheService $precacheService */
			$precacheService = App::getServiceByType(ResultsPrecacheService::class);
			$precacheService->prepareGamePrecache(...array_map(static fn(Game $game) => $game->code, $finishedGames));
		}
		else {
			$logger->info('No games to synchronize to public');
		}

		if (!empty($errors)) {
			$this->errorHandle($errors);
		}

		if ($this->cliFlag) {
			echo 'Successfully imported: ' . $imported . '/' . $total . ' in ' . round(
					microtime(true) - $start,
					2
				) . 's' . PHP_EOL;
			exit(0);
		}
		if ($this->apiFlag) {
			/* @phpstan-ignore-next-line */
			$this->controller->respond(
				[
					'imported' => $imported,
					'total'    => $total,
					'time'     => round(microtime(true) - $start, 2),
					'errors' => $this->errors,
				]
			);
		}
	}

	/**
	 * Handle an error message according to current controller
	 *
	 * @param string|Throwable|string[]|Throwable[] $data
	 * @param int                                   $statusCode
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function errorHandle(string|Throwable|array $data, int $statusCode = 0): void {
		if (!is_array($data)) {
			$data = [$data];
		}

		if ($this->cliFlag) {
			foreach ($data as $error) {
				$info = '';
				if (is_string($error)) {
					$info = $error;
				}
				else if ($error instanceof Throwable) {
					$info = 'An exception has occurred: ' . $error->getMessage();
					if ($error instanceof Exception) {
						$info .= ' - ' . $error->getSql();
					}
				}
				/* @phpstan-ignore-next-line */
				$this->controller->errorPrint($info);
			}
			if ($statusCode !== 0) {
				exit($statusCode);
			}
		}
		else if ($this->apiFlag) {
			$errors = [];
			foreach ($data as $error) {
				$info = [];
				if (is_string($error)) {
					$info['error'] = $error;
				}
				else if ($error instanceof \Exception) {
					$info = [
						'error'     => 'An exception has occurred.',
						'exception' => $error->getMessage(),
					];
					if ($error instanceof Exception) {
						$info['sql'] = $error->getSql();
					}
				}
				$errors[] = $info;
				if ($statusCode === 0) {
					// @phpstan-ignore-next-line
					$this->errors[] = $info;
				}
			}
			if ($statusCode !== 0) {
				/* @phpstan-ignore-next-line */
				$this->controller->respond(['errors' => $errors], $statusCode);
			}
		}
	}
}