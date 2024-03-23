<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Gate;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\Services\EventService;
use DateTime;
use Dibi\Exception;
use Dibi\Row;
use JsonException;
use Lsr\Core\Config;
use Lsr\Core\Constants;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Gate is a page that displays actual results and information preferably on other visible display.
 */
class Gate extends Controller
{

	use CommonGateMethods;

	public function __construct(
		Latte $latte,
		private readonly EventService $eventService,
		private readonly Config $config,
	) {
		parent::__construct($latte);
	}

	/**
	 * @return ResponseInterface
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function show(): ResponseInterface {
		$this->params['style'] = PrintStyle::getActiveStyle();

		// Allow for filtering games just from one system
		$system = $_GET['system'] ?? 'all';
		$systems = [$system];

		// Fallback to all available systems
		if ($system === 'all') {
			$systems = GameFactory::getSupportedSystems();
		}

		$now = time();

		$tmpGameResultsTime = (int)(
			$this->config->getConfig('ENV')['TMP_GAME_RESULTS_TIME']
			?? Constants::TMP_GAME_RESULTS_TIME
		);
		$gameResultsTime = (int)(
			$this->config->getConfig('ENV')['GAME_RESULTS_TIME']
			?? Constants::GAME_RESULTS_TIME
		);
		$gameStartedTime = (int)(
			$this->config->getConfig('ENV')['GAME_STARTED_TIME']
			?? Constants::GAME_STARTED_TIME
		);
		$gameLoadedTime = (int)(
			$this->config->getConfig('ENV')['GAME_LOADED_TIME']
			?? Constants::GAME_LOADED_TIME
		);

		// LAC allows for setting a game to display
		/** @var Game|null $test */
		$test = Info::get('gate-game');
		/** @var int $gateTime */
		$gateTime = Info::get('gate-time', $now);
		if (isset($test) && ($now - $gateTime) <= $tmpGameResultsTime) {
			$this->params['reloadTimer'] = $tmpGameResultsTime - ($now - $gateTime) + 2;
			$this->game = $test;
			return $this->getResults()->withHeader('X-Reload-Time', $this->params['reloadTimer']);
		}

		// Get the results of the last game played if it had finished in the last 2 minutes
		$lastGame = GameFactory::getLastGame($system);
		if (isset($lastGame) && ($now - $lastGame->end?->getTimestamp()) <= $gameResultsTime) {
			$this->params['reloadTimer'] = $gameResultsTime - ($now - $lastGame->end?->getTimestamp()) + 2;
			header('X-Reload-Time: ' . $this->params['reloadTimer']);
			$this->game = $lastGame;
			return $this->getResults();
		}

		// Try to find the last loaded or started games in selected systems
		foreach ($systems as $system) {
			/** @var Game|null $started */
			$started = Info::get($system . '-game-started');
			if (isset($started) && ($now - $started->start?->getTimestamp()) <= $gameStartedTime) {
				if (isset($this->game) && $this->game->fileTime > $started->fileTime) {
					continue;
				}
				$this->params['reloadTimer'] = $gameStartedTime - ($now - $started->start?->getTimestamp()) + 2;
				$started->end = null;
				$started->finished = false;
				$this->game = $started;
				continue;
			}

			/** @var Game|null $loaded */
			$loaded = Info::get($system . '-game-loaded');
			if (isset($loaded) && ($now - $loaded->fileTime?->getTimestamp()) <= $gameLoadedTime) {
				if (isset($this->game) && $this->game->fileTime > $loaded->fileTime) {
					continue;
				}
				$this->params['reloadTimer'] = $gameLoadedTime - ($now - $loaded->fileTime?->getTimestamp()) + 2;
				$this->game = $loaded;
			}
		}

		$response = (isset($this->game) && !$this->game->isStarted()) ? $this->getLoaded() : $this->getIdle();

		if (isset($this->params['reloadTimer'])) {
			return $response->withHeader('X-Reload-Time', $this->params['reloadTimer']);
		}
		return $response;
	}


	/**
	 * Get the loaded screen containing current players and their vests
	 *
	 * @pre Gate::$game must be set
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	private function getLoaded(): ResponseInterface {
		$this->params['game'] = $this->game;
		return $this->view('pages/gate/loaded');
	}

	/**
	 * Generate the idle screen containing today's statistics
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	private function getIdle(): ResponseInterface {
		$this->params['game'] = $this->game;
		$today = new DateTime();
		$games = GameFactory::queryGames(true, $today)->fetchAssoc('system|id_game', cache: false);
		/** @var array<string, int[]> $gameIds */
		$gameIds = [];
		$this->params['gameCount'] = 0;
		foreach ($games as $system => $g) {
			/** @var array<int, Row> $g */
			$gameIds[$system] = array_keys($g);
			$this->params['gameCount'] += count($g);
		}
		$playersQuery = PlayerFactory::queryPlayers($gameIds);

		$this->params['playerCount'] = empty($gameIds) ? 0 : $playersQuery->count();
		$this->params['teamCount'] = empty($gameIds) ? 0 : TeamFactory::queryTeams($gameIds)->count();

		$this->params['topScores'] = [];
		$this->params['topHits'] = null;
		$this->params['topDeaths'] = null;
		$this->params['topAccuracy'] = null;
		$this->params['topShots'] = null;

		if (!empty($gameIds)) {
			$q = PlayerFactory::queryPlayers($gameIds);
			$topScores = $q->orderBy('[score]')->desc()->fetchAssoc('name', cache: false);
			if (!empty($topScores)) {
				$count = 0;
				foreach ($topScores as $score) {
					$this->params['topScores'][] = PlayerFactory::getById(
						(int)$score->id_player,
						['system' => $score->system]
					);
					if ((++$count) > 3) {
						break;
					}
				}
			}
			$q = PlayerFactory::queryPlayers($gameIds);
			/** @var null|Row{id_player:int,system:string} $topHits */
			$topHits = $q->orderBy('[hits]')->desc()->fetch(cache: false);
			if (isset($topHits)) {
				$this->params['topHits'] = PlayerFactory::getById(
					(int)$topHits->id_player,
					['system' => $topHits->system]
				);
			}
			$q = PlayerFactory::queryPlayers($gameIds);
			/** @var null|Row{id_player:int,system:string} $topDeaths */
			$topDeaths = $q->orderBy('[deaths]')->desc()->fetch(cache: false);
			if (isset($topDeaths)) {
				$this->params['topDeaths'] = PlayerFactory::getById(
					(int)$topDeaths->id_player,
					['system' => $topDeaths->system]
				);
			}
			$q = PlayerFactory::queryPlayers($gameIds);
			/** @var null|Row{id_player:int,system:string} $topAccuracy */
			$topAccuracy = $q->orderBy('[accuracy]')->desc()->fetch(cache: false);
			if (isset($topAccuracy)) {
				$this->params['topAccuracy'] = PlayerFactory::getById(
					(int)$topAccuracy->id_player,
					['system' => $topAccuracy->system]
				);
			}
			$q = PlayerFactory::queryPlayers($gameIds);
			/** @var null|Row{id_player:int,system:string} $topShots */
			$topShots = $q->orderBy('[shots]')->desc()->fetch(cache: false);
			if (isset($topShots)) {
				$this->params['topShots'] = PlayerFactory::getById(
					(int)$topShots->id_player,
					['system' => $topShots->system]
				);
			}
		}
		return $this->view('pages/gate/idle');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateGame(Request $request): ResponseInterface {
		$game = $this->getGame($request);
		if ($game instanceof ErrorDto) {
			return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
		}
		try {
			$gateTime = time();
			Info::set('gate-game', $game);
			Info::set('gate-time', $gateTime);
			$this->eventService->trigger(
				'gate-reload',
				['type' => 'game-set', 'game' => $game->code, 'time' => $gateTime]
			);
		} catch (Exception $e) {
			return $this->respond(
				new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
				500
			);
		}
		return $this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return Game|ErrorDto
	 * @throws Throwable
	 */
	private function getGame(Request $request): Game|ErrorDto {
		$gameId = (int)$request->getPost('game', 0);
		if (empty($gameId)) {
			return new ErrorDto('Missing / Incorrect game', type: ErrorType::VALIDATION);
		}
		$system = $request->getParam('system');
		if (empty($system)) {
			return new ErrorDto('Missing / Incorrect system', type: ErrorType::VALIDATION);
		}
		$game = GameFactory::getById($gameId, ['system' => $system]);
		return $game ?? new ErrorDto('Cannot find game', type: ErrorType::NOT_FOUND);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateLoaded(Request $request): ResponseInterface {
		$game = $this->getGame($request);
		if ($game instanceof ErrorDto) {
			return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
		}
		$system = $request->params['system'] ?? '';
		try {
			Info::set('gate-game', null);
			$game->fileTime = new DateTime(); // Set time to NOW
			$game->start = null;
			Info::set($system . '-game-loaded', $game);
			$this->eventService->trigger(
				'gate-reload',
				['type' => 'game-set-loaded', 'game' => $game->code, 'time' => time()]
			);
		} catch (Exception $e) {
			return $this->respond(
				new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
				500
			);
		}
		return $this->respond(['success' => true]);
	}

	/**
	 * @param string $system
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function setGateIdle(string $system = ''): ResponseInterface {
		if (empty($system)) {
			return $this->respond(new ErrorDto('Missing / Incorrect system', type: ErrorType::VALIDATION), 400);
		}
		try {
			Info::set('gate-game', null);
			Info::set($system . '-game-loaded', null);
			$this->eventService->trigger('gate-reload', ['type' => 'set-idle', 'time' => time()]);
		} catch (Exception $e) {
			return $this->respond(
				new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
				500
			);
		}
		return $this->respond(['success' => true]);
	}

}