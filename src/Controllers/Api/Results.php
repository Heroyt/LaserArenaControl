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

	public function getLastGameFile(Request $request) : never {
		$resultsDir = urldecode($request->get['dir'] ?? '');
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}
		$resultFiles = glob($resultsDir.'*.game');
		// Sort by time
		usort($resultFiles, static function(string $a, string $b) {
			return filemtime(ROOT.$b) - filemtime(ROOT.$a);
		});
		header('Content-Type: text/plain');
		echo file_get_contents(ROOT.$resultFiles[0]);
		exit;
	}

}