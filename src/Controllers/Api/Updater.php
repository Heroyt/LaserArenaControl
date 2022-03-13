<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;

class Updater extends ApiController
{

	public function pull(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - pull ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('git stash 2>&1; git pull -r --ff-only 2>&1; git stash pop 2>&1;', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute git pull', 'errorCode' => $returnCode, 'output' => $output], 500);
		}
		$this->respond(['success' => true, 'output' => $output]);
	}

	public function fetch(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - fetch ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('git fetch 2>&1', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute git fetch', 'errorCode' => $returnCode, 'output' => $output], 500);
		}
		$this->respond(['success' => true, 'output' => $output]);
	}

	public function status(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - pull ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('git status 2>&1', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute git status', 'errorCode' => $returnCode, 'output' => $output], 500);
		}
		$this->respond(['success' => true, 'output' => $output]);
	}

	public function build(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - build ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('composer build 2>&1', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute build', 'errorCode' => $returnCode], 500);
		}
		$this->respond(['success' => true]);
	}

}