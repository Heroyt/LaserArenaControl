<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Info;
use App\Core\Request;
use App\Exceptions\FileException;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
use App\Tools\Evo5\ResultsParser;
use Dibi\Exception;

class Results extends ApiController
{

	public function import(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'results/', 'import');
		} catch (DirectoryCreationException $e) {
			$this->respond(['error' => $e->getMessage()], 500);
		}
		$resultsDir = $request->post['dir'] ?? '';
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}
		if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
			$this->respond(['error' => 'Results directory does not exist.'], 400);
		}
		if (substr($resultsDir, -1) !== DIRECTORY_SEPARATOR) {
			$resultsDir .= DIRECTORY_SEPARATOR;
		}
		$resultFiles = glob($resultsDir.'*.game');
		$lastCheck = (int) Info::get($resultsDir.'check', 0);
		$imported = 0;
		$total = 0;
		$start = microtime(true);
		$errors = [];
		foreach ($resultFiles as $file) {
			if (str_ends_with($file, '0000.game')) {
				continue;
			}
			if (filemtime($file) > $lastCheck) {
				$total++;
				echo 'Importing: '.$file.PHP_EOL;
				$logger->info('Importing file: '.$file);
				try {
					$parser = new ResultsParser($file);
					$game = $parser->parse();
					if (!isset($game->end)) {
						continue;
					}
					if (!$game->save()) {
						throw new ResultsParseException('Failed saving game into DB.');
					}
					$imported++;
				} catch (FileException|GameModeNotFoundException|ResultsParseException|ValidationException $e) {
					$logger->error($e->getMessage());
					$logger->debug($e->getTraceAsString());
					$errors[] = $e->getMessage();
				}
			}
		}
		try {
			Info::set($resultsDir.'check', time());
		} catch (Exception $e) {
			$this->respond(
				[
					'error'     => 'Failed to save the last check time.',
					'exception' => $e->getMessage(),
					'sql'       => $e->getSql()
				],
				500
			);
		}
		$this->respond(
			[
				'imported' => $imported,
				'total'    => $total,
				'time'     => round(microtime(true) - $start, 2),
				'errors'   => $errors,
			]
		);
	}

}