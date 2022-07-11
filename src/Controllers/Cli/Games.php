<?php

namespace App\Controllers\Cli;

use App\Services\ImportService;
use App\Services\SyncService;
use Lsr\Core\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Helpers\Cli\CliHelper;

/**
 *
 */
class Games extends CliController
{

	/**
	 * Import games from a given directory
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 */
	public function import(CliRequest $request) : void {
		$resultsDir = $request->args[0] ?? '';
		if (empty($resultsDir)) {
			CliHelper::printErrorMessage('Argument 0 is required. Valid results directory is expected.');
			exit(1);
		}

		ImportService::import($resultsDir, $this);
	}

	/**
	 * Synchronize games to public API
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 */
	public function sync(CliRequest $request) : void {
		$limit = (int) ($request->args[0] ?? 5);
		$timeout = isset($request->args[1]) ? (float) $request->args[1] : null;
		SyncService::syncGames($limit, $timeout);
	}

}