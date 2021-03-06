<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Services\EventService;
use DateTime;
use Dibi\Exception;
use Dibi\Row;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Writer\SvgWriter;
use Lsr\Core\App;
use Lsr\Core\Constants;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Throwable;

/**
 * Gate is a page that displays actual results and information preferably on other visible display.
 */
class Gate extends Controller
{

	protected ?Game $game = null;

	/**
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function show() : void {
		$this->params['style'] = PrintStyle::getActiveStyle();

		// Allow for filtering games just from one system
		$system = $_GET['system'] ?? 'all';
		$systems = [$system];

		// Fallback to all available systems
		if ($system === 'all') {
			$systems = GameFactory::getSupportedSystems();
		}

		$now = time();

		// LAC allows for setting a game to display
		/** @var Game|null $test */
		$test = Info::get('gate-game');
		/** @var int $gateTime */
		$gateTime = Info::get('gate-time', $now);
		if (isset($test) && ($now - $gateTime) <= Constants::TMP_GAME_RESULTS_TIME) {
			$this->params['reloadTimer'] = Constants::TMP_GAME_RESULTS_TIME - ($now - $gateTime) + 2;
			header('X-Reload-Time: '.$this->params['reloadTimer']);
			$this->game = $test;
			$this->getResults();
			return;
		}

		// Get the results of the last game played if it had finished in the last 2 minutes
		$lastGame = GameFactory::getLastGame($system);
		if (isset($lastGame) && ($now - $lastGame->end?->getTimestamp()) <= Constants::GAME_RESULTS_TIME) {
			$this->params['reloadTimer'] = Constants::GAME_RESULTS_TIME - ($now - $lastGame->end?->getTimestamp()) + 2;
			header('X-Reload-Time: '.$this->params['reloadTimer']);
			$this->game = $lastGame;
			$this->getResults();
			return;
		}

		// Try to find the last loaded or started games in selected systems
		foreach ($systems as $system) {
			/** @var Game|null $started */
			$started = Info::get($system.'-game-started');
			if (isset($started) && ($now - $started->start?->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $started->fileTime) {
					continue;
				}
				$this->params['reloadTimer'] = Constants::GAME_STARTED_TIME - ($now - $started->start?->getTimestamp()) + 2;
				$started->end = null;
				$started->finished = false;
				$this->game = $started;
				continue;
			}

			/** @var Game|null $loaded */
			$loaded = Info::get($system.'-game-loaded');
			if (isset($loaded) && ($now - $loaded->fileTime?->getTimestamp()) <= Constants::GAME_LOADED_TIME) {
				if (isset($this->game) && $this->game->fileTime > $loaded->fileTime) {
					continue;
				}
				$this->params['reloadTimer'] = Constants::GAME_LOADED_TIME - ($now - $loaded->fileTime?->getTimestamp()) + 2;
				$this->game = $loaded;
			}
		}
		if (isset($this->params['reloadTimer'])) {
			header('X-Reload-Time: '.$this->params['reloadTimer']);
		}

		if (isset($this->game) && !$this->game->isStarted()) {
			$this->getLoaded();
			return;
		}

		$this->getIdle();
	}

	/**
	 * Display the results of the game
	 *
	 * @pre Gate::$game must be set
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	private function getResults() : void {
		if (!isset($this->game)) {
			App::redirect(['E404']);
		}
		$this->params['game'] = $this->game;
		$this->params['qr'] = $this->getQR($this->game);
		$namespace = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($this->game::SYSTEM).'\\';
		$teamClass = $namespace.'Team';
		$playerClass = $namespace.'Player';
		/** @var Player $player */
		$player = new $playerClass;
		/** @var Team $team */
		$team = new $teamClass;
		$this->params['today'] = new Today($this->game, $player, $team);
		if (isset($this->game->mode) && $this->game->mode instanceof CustomResultsMode) {
			$this->view(
				$this->game->mode->getCustomGateTemplate($this)
			);
		}
		else {
			$this->view('pages/gate/results');
		}
	}

	/**
	 * Get SVG QR code for game
	 *
	 * @param Game $game
	 *
	 * @return string
	 */
	private function getQR(Game $game) : string {
		$result = Builder::create()
										 ->data($this->getPublicUrl($game))
										 ->writer(new SvgWriter())
										 ->encoding(new Encoding('UTF-8'))
										 ->errorCorrectionLevel(new ErrorCorrectionLevelLow())
										 ->build();
		return $result->getString();
	}

	private function getPublicUrl(Game $game) : string {
		/** @var string $url */
		$url = Info::get('liga_api_url');
		return trailingSlashIt($url).'g/'.$game->code;
	}

	/**
	 * Get the loaded screen containing current players and their vests
	 *
	 * @pre Gate::$game must be set
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	private function getLoaded() : void {
		$this->params['game'] = $this->game;
		$this->view('pages/gate/loaded');
	}

	/**
	 * Generate the idle screen containing today's statistics
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	private function getIdle() : void {
		$this->params['game'] = $this->game;
		$today = new DateTime();
		$games = GameFactory::queryGames(true, $today)->fetchAssoc('system|id_game');
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
			$q = clone $playersQuery;
			$topScores = $q->orderBy('[score]')->desc()->fetchAssoc('name');
			if (!empty($topScores)) {
				$count = 0;
				foreach ($topScores as $score) {
					/** @phpstan-ignore-next-line */
					$this->params['topScores'][] = PlayerFactory::getById($score->id_player, ['system' => $score->system]);
					if ((++$count) > 3) {
						break;
					}
				}
			}
			$q = clone $playersQuery;
			/** @var null|Row{id_player:int,system:string} $topHits */
			$topHits = $q->orderBy('[hits]')->desc()->fetch();
			if (isset($topHits)) {
				$this->params['topHits'] = PlayerFactory::getById($topHits->id_player, ['system' => $topHits->system]);
			}
			$q = clone $playersQuery;
			/** @var null|Row{id_player:int,system:string} $topDeaths */
			$topDeaths = $q->orderBy('[deaths]')->desc()->fetch();
			if (isset($topDeaths)) {
				$this->params['topDeaths'] = PlayerFactory::getById($topDeaths->id_player, ['system' => $topDeaths->system]);
			}
			$q = clone $playersQuery;
			/** @var null|Row{id_player:int,system:string} $topAccuracy */
			$topAccuracy = $q->orderBy('[accuracy]')->desc()->fetch();
			if (isset($topAccuracy)) {
				$this->params['topAccuracy'] = PlayerFactory::getById($topAccuracy->id_player, ['system' => $topAccuracy->system]);
			}
			$q = clone $playersQuery;
			/** @var null|Row{id_player:int,system:string} $topShots */
			$topShots = $q->orderBy('[shots]')->desc()->fetch();
			if (isset($topShots)) {
				$this->params['topShots'] = PlayerFactory::getById($topShots->id_player, ['system' => $topShots->system]);
			}
		}
		$this->view('pages/gate/idle');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateGame(Request $request) : void {
		$gameId = (int) ($request->post['game'] ?? 0);
		if (empty($gameId)) {
			$this->respond(['error' => 'Missing / Incorrect game'], 400);
		}
		$system = $request->params['system'] ?? '';
		if (empty($system)) {
			$this->respond(['error' => 'Missing / Incorrect system'], 400);
		}
		$game = GameFactory::getById($gameId, ['system' => $system]);
		if (!isset($game)) {
			$this->respond(['error' => 'Cannot find game'], 404);
		}
		try {
			Info::set('gate-game', $game);
			Info::set('gate-time', time());
			EventService::trigger('gate-reload');
		} catch (Exception $e) {
			$this->respond(['error' => 'Failed to save the game info', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateLoaded(Request $request) : void {
		$gameId = (int) ($request->post['game'] ?? 0);
		if (empty($gameId)) {
			$this->respond(['error' => 'Missing / Incorrect game'], 400);
		}
		$system = $request->params['system'] ?? '';
		if (empty($system)) {
			$this->respond(['error' => 'Missing / Incorrect system'], 400);
		}
		$game = GameFactory::getById($gameId, ['system' => $system]);
		if (!isset($game)) {
			$this->respond(['error' => 'Cannot find game'], 404);
		}
		try {
			Info::set('gate-game', null);
			$game->fileTime = new DateTime(); // Set time to NOW
			$game->start = null;
			Info::set($system.'-game-loaded', $game);
			EventService::trigger('gate-reload');
		} catch (Exception $e) {
			$this->respond(['error' => 'Failed to save the game info', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['success' => true]);
	}

}