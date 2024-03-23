<?php

namespace App\Controllers\Api;

use Lsr\Core\Caching\Cache as CacheService;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class Cache extends Controller
{

	public function __construct(protected Latte $latte, protected CacheService $cache) {
		parent::__construct($latte);
	}

	public function clearAll(): ResponseInterface {
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
		/** @var string[] $files */
		$files = array_merge(
			glob(TMP_DIR . '*.cache'),
			glob(TMP_DIR . '*.php'),
			glob(TMP_DIR . '*.php.lock'),
			glob(TMP_DIR . 'di/*'),
			glob(TMP_DIR . 'results/*'),
			glob(TMP_DIR . 'resultCaches/*'),
			glob(TMP_DIR . 'latte/*'),
			glob(TMP_DIR . 'models/*'),
		);
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	public function clearSystem(): ResponseInterface {
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
		return $this->respond(['status' => 'ok']);
	}

	public function clearDi(): ResponseInterface {
		/** @var string[] $files */
		$files = array_merge(
			glob(TMP_DIR . '*.php'),
			glob(TMP_DIR . '*.php.lock'),
			glob(TMP_DIR . 'di/*'),
		);
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	public function clearModels(): ResponseInterface {
		/** @var string[] $files */
		$files = glob(TMP_DIR . 'models/*');
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	public function clearConfig(): ResponseInterface {
		/** @var string[] $files */
		$files = glob(TMP_DIR . '*.cache');
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	public function clearResults(): ResponseInterface {
		/** @var string[] $files */
		$files = array_merge(
			glob(TMP_DIR . 'results/*'),
			glob(TMP_DIR . 'resultCaches/*'),
		);
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	public function clearLatte(): ResponseInterface {
		/** @var string[] $files */
		$files = glob(TMP_DIR . 'latte/*');
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

}