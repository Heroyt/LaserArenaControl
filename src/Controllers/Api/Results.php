<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Services\ImportService;

class Results extends ApiController
{

	public function import(Request $request) : void {
		$resultsDir = $request->post['dir'] ?? '';
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}

		ImportService::import($resultsDir, $this);
	}

}