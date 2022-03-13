<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;

class Debug extends ApiController
{

	public function pwd(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'mount');
			$logger->info('Executing pwd ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('pwd 2>&1', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute pwd', 'errorCode' => $returnCode], 500);
		}
		$this->respond(['success' => true, 'output' => $out]);
	}

	public function whoami(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'mount');
			$logger->info('Executing whoami ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('whoami 2>&1', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute whoami', 'errorCode' => $returnCode], 500);
		}
		$this->respond(['success' => true, 'output' => $out]);
	}

}