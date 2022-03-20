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
		$resultsDir = trailingSlashIt($resultsDir);
		$resultFiles = glob(ROOT.$resultsDir.'*.game');
		// Sort by time
		usort($resultFiles, static function(string $a, string $b) {
			return filemtime($b) - filemtime($a);
		});
		$this->respond(['files' => $resultFiles, 'contents1' => utf8_encode(file_get_contents($resultFiles[0])), 'contents2' => utf8_encode(file_get_contents($resultFiles[1]))]);
	}

}