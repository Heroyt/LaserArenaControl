<?php

namespace App\Controllers\Api;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Team;
use App\Models\GameGroup;
use App\Services\Evo5\GameSimulator;
use App\Services\GameHighlight\GameHighlightService;
use App\Services\SyncService;
use DateTimeImmutable;
use Exception;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	public function __construct(
		Latte                                 $latte,
		private readonly GameSimulator        $gameSimulator,
		private readonly GameHighlightService $highlightService,
	) {
		parent::__construct($latte);
	}

	public function cheat(Request $request) : ResponseInterface {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			return $this->respond(['error' => 'Invalid code'], 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			return $this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		$player = $request->getPost('player', 0);
		if (empty($player)) {
			return $this->respond(['error' => 'Invalid player'], 400);
		}

		$playerObj = $game->getPlayers()->get($player);
		if (!isset($playerObj)) {
			return $this->respond(['error' => 'Player not found'], 404);
		}

		$enemies = [];
		if ($game->getMode()?->isTeam()) {
			/** @var Team $team */
			foreach ($game->getTeams() as $team) {
				if ($team->color === $playerObj->getTeam()->color) {
					continue;
				}
				foreach ($team->getPlayers() as $player2) {
					$enemies[] = $player2;
				}
			}
		}

		$addHits = $request->getGet('addHits');
		if (isset($addHits)) {
			$hits = (int) $addHits;
			$playerObj->hits += $hits;
			for ($i = 0; $i < $hits; $i++) {
				$enemy = $enemies[array_rand($enemies)];
			}
		}

		return $this->respond($game);
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws Throwable
	 */
	public function syncGames(Request $request) : ResponseInterface {
		$limit = (int) ($request->params['limit'] ?? 5);
		$timeout = $request->getGet('timeout');
		$timeout = isset($timeout) ? (float) $timeout : null;
		SyncService::syncGames($limit, $timeout);
		return $this->respond(['success' => true]);
	}

	/**
	 *
	 * @return ResponseInterface
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 */
	public function syncGame(string $code) : ResponseInterface {
		if (empty($code)) {
			return $this->respond(['error' => 'Invalid code'], 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			return $this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		if (!$game->sync()) {
			return $this->respond(['error' => 'Synchronization failed'], 500);
		}

		return $this->respond(['status' => 'ok']);
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws Throwable
	 */
	public function listGames(Request $request) : ResponseInterface {
		$date = $request->getGet('date');
		if (!empty($date)) {
			try {
				$date = new DateTimeImmutable($date);
			} catch (Exception $e) {
				return $this->respond(['error' => 'Invalid parameter: "date"', 'exception' => $e->getMessage()], 400);
			}
		}
		else {
			$date = null;
		}
		// TODO: Possibly more filters
		$query = GameFactory::queryGames($request->getGet('excludeFinished') !== null, $date);

		$limit = $request->getGet('limit');
		$offset = $request->getGet('offset');
		$system = $request->getGet('system');
		$orderBy = $request->getGet('orderBy');
		$desc = $request->getGet('desc');

		if (!empty($limit) && is_numeric($limit)) {
			$query->limit((int) $limit);
		}
		if (!empty($offset) && is_numeric($offset)) {
			$query->offset((int) $offset);
		}
		if (!empty($system)) {
			$query->where('[system] = %s', $system);
		}
		if (!empty($orderBy)) {
			if (!in_array($orderBy, ['start', 'end', 'code', 'id_game'], true)) {
				return $this->respond(['error' => 'Invalid orderBy field: '.$orderBy], 400);
			}
			$query->orderBy($orderBy);
			if (!empty($desc)) {
				$query->desc();
			}
		}
		$games = $query->fetchAll(cache: false);
		if (!empty($request->getGet('expand'))) {
			$objects = [];
			foreach ($games as $row) {
				$objects[] = GameFactory::getByCode($row->code);
			}
			return $this->respond($objects);
		}
		return $this->respond($games);
	}

	/**
	 * Get one game's data by its code
	 *
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws Throwable
	 */
	public function getGame(Request $request) : ResponseInterface {
		$gameCode = $request->params['code'] ?? '';
		if (empty($gameCode)) {
			return $this->respond(['error' => 'Invalid code'], 400);
		}
		$game = GameFactory::getByCode($gameCode);
		if (!isset($game)) {
			return $this->respond(['error' => 'Game not found'], 404);
		}
		return $this->respond($game);
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws Throwable
	 */
	public function setGroup(string $code, Request $request) : ResponseInterface {
		if (empty($code)) {
			return $this->respond(['error' => 'Invalid code'], 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			return $this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		$group = $request->getPost('groupId', 0);
		if ($group > 0) {
			try {
				$game->group = GameGroup::get($group);
			} catch (ModelNotFoundException | ValidationException | DirectoryCreationException $e) {
				return $this->respond(
					['error' => 'Game group not found', 'exception' => $e->getMessage(), 'trace' => $e->getTrace()],
					404
				);
			}
		}
		else {
			$game->group = null;
		}

		try {
			$game->save();
			$game->sync();
		} catch (ModelNotFoundException | ValidationException $e) {
			return $this->respond(['error' => 'Save failed', 'exception' => $e->getMessage()], 500);
		}

		return $this->respond(['success' => true]);
	}

	public function simulate() : ResponseInterface {
		$this->gameSimulator->simulate();
		return $this->respond(['success' => true]);
	}

	public function getHighlights(string $code, Request $request) : ResponseInterface {

		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			return $this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		return $this->respond(
			$this->highlightService->getHighlightsForGame(
				$game,
				!$request->getGet('no-cache')
			)
		);
	}

}