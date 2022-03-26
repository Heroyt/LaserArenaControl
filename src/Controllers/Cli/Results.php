<?php

namespace App\Controllers\Cli;

use App\Core\CliController;
use App\Core\CliRequest;
use App\Services\CliHelper;
use App\Services\ImportService;

class Results extends CliController
{

	public function import(CliRequest $request) : void {
		$resultsDir = $request->args[0] ?? '';
		if (empty($resultsDir)) {
			CliHelper::printErrorMessage('Argument 0 is required. Valid results directory is expected.');
			exit(1);
		}

		ImportService::import($resultsDir, $this);
	}

}