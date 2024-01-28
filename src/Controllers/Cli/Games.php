<?php

namespace App\Controllers\Cli;

use App\GameModels\Factory\GameFactory;
use App\Services\ImportService;
use App\Services\SyncService;
use Lsr\Core\Controllers\CliController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\CliRequest;
use Lsr\Core\Routing\Attributes\Cli;
use Lsr\Helpers\Cli\CliHelper;
use Throwable;

/**
 *
 */
class Games extends CliController
{

	public function __construct(
		private readonly ImportService $importService
	) {
	}

	/**
	 * Import games from a given directory
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function import(CliRequest $request) : void {
		$resultsDir = $request->args[0] ?? '';
		if (empty($resultsDir)) {
			CliHelper::printErrorMessage('Argument 0 is required. Valid results directory is expected.');
			exit(1);
		}

		$this->importService->import($resultsDir, $this);
	}

	/**
	 * Synchronize games to public API
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function sync(CliRequest $request) : void {
		$limit = (int) ($request->args[0] ?? 5);
		$timeout = isset($request->args[1]) ? (float) $request->args[1] : null;
		SyncService::syncGames($limit, $timeout);
	}

	#[Cli('games/skill')]
	public function recalcSkill(CliRequest $request) : void {
		foreach (GameFactory::queryGames(true)->orderBy('start')->desc()->getIterator((int) ($request->args[0] ?? 0), (int) ($request->args[1] ?? 200)) as $row) {
			$game = GameFactory::getByCode($row->code);
			if (!isset($game)) {
				continue;
			}
			$game->calculateSkills();
			$game->save();
			unset($game);
		}
	}

}