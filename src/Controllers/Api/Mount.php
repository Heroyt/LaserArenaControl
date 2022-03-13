<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;

class Mount extends ApiController
{

	public function mount(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'mount');
			$logger->info('Remounting all ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('mount -a', $output, $returnCode);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute mount', 'errorCode' => $returnCode], 500);
		}
		$this->respond(['success' => true]);
	}

}