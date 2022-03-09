<?php

namespace App\Controllers\Cli;

use App\Core\CliController;
use App\Core\CliRequest;
use App\Core\Info;
use App\Exceptions\FileException;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
use App\Services\EventService;
use App\Tools\Evo5\ResultsParser;
use Dibi\Exception;

class Results extends CliController
{

	public function import(CliRequest $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'results/', 'import');
		} catch (DirectoryCreationException $e) {
			fwrite(STDERR, $e->getMessage().PHP_EOL);
			exit(2);
		}
		$resultsDir = $request->args[0] ?? '';
		if (empty($resultsDir)) {
			fwrite(STDERR, 'Argument 0 is required. Valid results directory is expected.'.PHP_EOL);
			exit(1);
		}
		if (!file_exists($resultsDir) || !is_dir($resultsDir) || !is_readable($resultsDir)) {
			fwrite(STDERR, 'Results directory does not exist.'.PHP_EOL);
			exit(1);
		}
		if (substr($resultsDir, -1) !== DIRECTORY_SEPARATOR) {
			$resultsDir .= DIRECTORY_SEPARATOR;
		}
		$resultFiles = glob($resultsDir.'*.game');
		$lastCheck = (int) Info::get($resultsDir.'check', 0);
		$imported = 0;
		$total = 0;
		$start = microtime(true);
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
					fwrite(STDERR, $e->getMessage().PHP_EOL);
				}
			}
		}
		try {
			Info::set($resultsDir.'check', time());
		} catch (Exception $e) {
			fwrite(STDERR, 'Failed to save the last check time: '.$e->getMessage().PHP_EOL.$e->getSql().PHP_EOL);
		}

		// Send event on new import
		if ($imported > 0) {
			EventService::trigger('game-imported');
		}

		echo 'Successfully imported: '.$imported.'/'.$total.' in '.round(microtime(true) - $start, 2).'s'.PHP_EOL;
		exit(0);
	}

}