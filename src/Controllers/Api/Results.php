<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Logging\ArchiveCreationException;
use App\Services\ImportService;
use ZipArchive;

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

	public function downloadLastGameFiles(Request $request) : never {
		/** TODO: Secure.. this is really bad.. and would allow for download of any files */
		$resultsDir = urldecode($request->get['dir'] ?? '');
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}
		$resultsDir = trailingSlashIt($resultsDir);
		$resultFiles = glob(ROOT.$resultsDir.'*.game');

		$archive = new ZipArchive();
		$test = $archive->open(TMP_DIR.'games.zip', ZipArchive::CREATE); // Create or open a zip file
		if ($test !== true) {
			throw new ArchiveCreationException($test);
		}

		foreach ($resultFiles as $file) {
			$fileName = str_replace(ROOT.$resultsDir, '', $file);
			$archive->addFile($file, $fileName);
		}
		$archive->close();

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="games.zip"');
		header("Content-Length: ".filesize(TMP_DIR.'games.zip'));
		http_response_code(200);
		readfile(TMP_DIR.'games.zip');
		exit;
	}

}