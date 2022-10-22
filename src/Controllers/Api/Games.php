<?php

namespace App\Controllers\Api;

use App\GameModels\Factory\GameFactory;
use App\Models\GameGroup;
use App\Services\SyncService;
use DateTime;
use Exception;
use JsonException;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function syncGames(Request $request) : void {
		$limit = (int) ($request->params['limit'] ?? 5);
		$timeout = isset($request->get['timeout']) ? (float) $request->get['timeout'] : null;
		SyncService::syncGames($limit, $timeout);
		$this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ModelNotFoundException
	 */
	#[Post('/api/games/{code}/sync')]
	public function syncGame(Request $request) : never {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			$this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		if (!$game->sync()) {
			$this->respond(['error' => 'Synchronization failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function listGames(Request $request) : void {
		$date = null;
		if (!empty($request->get['date'])) {
			try {
				$date = new DateTime($request->get['date']);
			} catch (Exception $e) {
				$this->respond(['error' => 'Invalid parameter: "date"', 'exception' => $e->getMessage()], 400);
			}
		}
		// TODO: Possibly more filters
		$query = GameFactory::queryGames(isset($request->get['excludeFinished']), $date);
		if (!empty($request->get['limit']) && is_numeric($request->get['limit'])) {
			$query->limit((int) $request->get['limit']);
		}
		if (!empty($request->get['offset']) && is_numeric($request->get['offset'])) {
			$query->offset((int) $request->get['offset']);
		}
		if (!empty($request->get['system'])) {
			$query->where('[system] = %s', $request->get['system']);
		}
		if (!empty($request->get['orderBy'])) {
			if (!in_array($request->get['orderBy'], ['start', 'end', 'code', 'id_game'], true)) {
				$this->respond(['error' => 'Invalid orderBy field: '.$request->get['orderBy']], 400);
			}
			$query->orderBy($request->get['orderBy']);
			if (!empty($request->get['desc'])) {
				$query->desc();
			}
		}
		$games = $query->fetchAll();
		if (!empty($request->get['expand'])) {
			$objects = [];
			foreach ($games as $row) {
				$objects[] = GameFactory::getByCode($row->code);
			}
			$this->respond($objects);
		}
		$this->respond($games);
	}

	/**
	 * Get one game's data by its code
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function getGame(Request $request) : void {
		$gameCode = $request->params['code'] ?? '';
		if (empty($gameCode)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		$game = GameFactory::getByCode($gameCode);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		$this->respond($game);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	#[Post('/api/games/{code}/group')]
	public function setGroup(Request $request) : void {
		$code = $request->params['code'] ?? '';
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			$this->respond(['error' => 'Game not found', 'exception' => $e->getMessage()], 404);
		}

		$group = $request->post['groupId'] ?? 0;
		if ($group > 0) {
			try {
				$game->group = GameGroup::get($group);
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
				$this->respond(['error' => 'Game group not found', 'exception' => $e->getMessage(), 'trace' => $e->getTrace()], 404);
			}
		}
		else {
			$game->group = null;
		}

		try {
			$game->save();
		} catch (ModelNotFoundException|ValidationException $e) {
			$this->respond(['error' => 'Save failed', 'exception' => $e->getMessage()], 500);
		}

		$this->respond(['success' => true]);
	}

}