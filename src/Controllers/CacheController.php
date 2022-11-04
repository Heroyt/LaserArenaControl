<?php

namespace App\Controllers;

use Lsr\Core\Caching\Cache;
use Lsr\Core\Controller;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;

class CacheController extends Controller
{

	public function __construct(protected Latte $latte, protected Cache $cache) {
		parent::__construct($latte);
	}

	#[Post('/cache/clear', 'cache-clear')]
	public function clearAll() : void {
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
		/** @var string[] $files */
		$files = array_merge(
			glob(TMP_DIR.'di/*'),
			glob(TMP_DIR.'results/*'),
			glob(TMP_DIR.'resultCaches/*'),
			glob(TMP_DIR.'latte/*'),
		);
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		$this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	#[Post('/cache/clear/system', 'cache-clear-system')]
	public function clearSystem() : void {
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
		$this->respond(['status' => 'ok']);
	}

	#[Post('/cache/clear/di', 'cache-clear-di')]
	public function clearDi() : void {
		/** @var string[] $files */
		$files = glob(TMP_DIR.'di/*');
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		$this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	#[Post('/cache/clear/results', 'cache-clear-results')]
	public function clearResults() : void {
		/** @var string[] $files */
		$files = array_merge(
			glob(TMP_DIR.'results/*'),
			glob(TMP_DIR.'resultCaches/*'),
		);
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		$this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

	#[Post('/cache/clear/latte', 'cache-clear-latte')]
	public function clearLatte() : void {
		/** @var string[] $files */
		$files = glob(TMP_DIR.'latte/*');
		$deleted = 0;
		foreach ($files as $file) {
			if (unlink($file)) {
				$deleted++;
			}
		}
		$this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
	}

}