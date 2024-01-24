<?php

namespace App\Controllers\Api;

use App\Core\App;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;

class Debug extends ApiController
{

	/**
	 * @return void
	 * @throws JsonException
	 */
	public function disable() : void {
		/** @var string $contents */
		$contents = file_get_contents(PRIVATE_DIR.'config.ini');
		if (file_put_contents(PRIVATE_DIR.'config.ini', str_replace('DEBUG = true', 'DEBUG = false', $contents)) === false) {
			$this->respond(['error' => 'Cannot write to config file.'], 500);
		}
		$this->respond(['success' => true]);
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	public function incrementCache() : void {
		$version = App::getCacheVersion();
		/** @var string $contents */
		$contents = file_get_contents(PRIVATE_DIR.'config.ini');
		if (file_put_contents(PRIVATE_DIR.'config.ini', str_replace('CACHE_VERSION = '.$version, 'CACHE_VERSION = '.($version + 1), $contents)) === false) {
			$this->respond(['error' => 'Cannot write to config file.'], 500);
		}
		$this->respond(['success' => true]);
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	public function enable() : void {
		/** @var string $contents */
		$contents = file_get_contents(PRIVATE_DIR.'config.ini');
		if (file_put_contents(PRIVATE_DIR.'config.ini', str_replace('DEBUG = false', 'DEBUG = true', $contents)) === false) {
			$this->respond(['error' => 'Cannot write to config file.'], 500);
		}
		$this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function pwd(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'mount');
			$logger->info('Executing pwd ('.$request->getIp().')');
		} catch (DirectoryCreationException) {
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

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function glob(Request $request) : void {
		$param = urldecode($request->get['param'] ?? '');
		if (empty($param)) {
			$this->respond(['error' => 'Missing required argument "param".'], 400);
		}
		$this->respond(['success' => true, 'output' => glob($param)]);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function whoami(Request $request) : void {
		try {
			$logger = new Logger(LOG_DIR.'api/', 'mount');
			$logger->info('Executing whoami ('.$request->getIp().')');
		} catch (DirectoryCreationException) {
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