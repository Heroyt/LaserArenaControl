<?php

namespace App\Controllers\Api;

use App\GameModels\Factory\GameFactory;
use App\Services\SyncService;
use DateTime;
use Exception;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	/**
	 * @param Request $request
	 *
	 * @return void
	 */
	public function syncGames(Request $request) : void {
		$limit = (int) ($request->params['limit'] ?? 5);
		$timeout = isset($request->get['timeout']) ? (float) $request->get['timeout'] : null;
		SyncService::syncGames($limit, $timeout);
		$this->respond(['success' => true]);
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @return void
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
		$query = GameFactory::queryGames(false, $date);
		$games = $query->fetchAll();
		$this->respond($games);
	}

	/**
	 * Get one game's data by its code
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public function getGame(Request $request) : void {
		$gameCode = $request->params['code'] ?? '';
		if (empty($gameCode)) {
			$this->respond(['Invalid code'], 400);
		}
		$game = GameFactory::getByCode($gameCode);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		$this->respond($game);
	}

}