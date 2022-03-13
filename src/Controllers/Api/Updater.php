<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Request;
use App\Install\Install;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;

class Updater extends ApiController
{

	/**
	 * Pull changes from remote using an API route
	 *
	 * @param Request $request
	 *
	 * @return void
	 */
	public function pull(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - pull ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		/** @var string|false $out */
		$out = exec('git stash push -u 2>&1; git pull 2>&1;', $output, $returnCode);
		exec('git stash pop 2>&1', $output2);
		$output = array_merge($output, $output2);

		if ($out === false || $returnCode !== 0) {
			$logger?->warning('Cannot execute command');
			$logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
			$logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
			$this->respond(['error' => 'Cannot execute git pull', 'errorCode' => $returnCode, 'output' => $output], 500);
		}
		$this->respond(['success' => true, 'output' => $output]);
	}

	/**
	 * Fetch changes from remote using an API route
	 *
	 * @param Request $request
	 *
	 * @return void
	 */
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

	/**
	 * Get GIT status using an API route
	 *
	 * @param Request $request
	 *
	 * @return void
	 */
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

	/**
	 * Build all assets using an API route
	 *
	 * @param Request $request
	 *
	 * @return void
	 */
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

	/**
	 * Install the database changes
	 *
	 * @param Request $request
	 *
	 * @return void
	 */
	public function install(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'update');
			$logger->info('Updating LAC - install ('.$request->getIp().')');
		} catch (DirectoryCreationException $e) {
			$logger = null;
		}

		ob_start();
		$success = Install::install(isset($request->post['fresh']) && $request->post['fresh'] === 1);
		$output = ob_get_clean();

		if (!$success) {
			$logger?->warning('Install failed');
			$logger?->debug($output);
			$this->respond(['error' => 'Install failed', 'output' => $output], 500);
		}
		$this->respond(['success' => true, 'output' => $output]);
	}

}