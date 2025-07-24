<?php

namespace App\Controllers\Api;

use Lsr\Caching\Cache as CacheService;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class Cache extends Controller
{
    public function __construct(protected CacheService $cache) {}

    public function clearAll() : ResponseInterface {
        $this->cache->clean([\Nette\Caching\Cache::All => true]);
        $cache = glob(TMP_DIR.'*.cache');
        $php = glob(TMP_DIR.'*.php');
        $phpLock = glob(TMP_DIR.'*.php.lock');
        $di = glob(TMP_DIR.'di/*');
        $results = glob(TMP_DIR.'results/*');
        $resultsCache = glob(TMP_DIR.'resultCaches/*');
        $latte = glob(TMP_DIR.'latte/*');
        $models = glob(TMP_DIR.'models/*');
        $files = array_merge(
          $cache === false ? [] : $cache,
          $php === false ? [] : $php,
          $phpLock === false ? [] : $phpLock,
          $di === false ? [] : $di,
          $results === false ? [] : $results,
          $resultsCache === false ? [] : $resultsCache,
          $latte === false ? [] : $latte,
          $models === false ? [] : $models,
        );
        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }

    public function clearSystem() : ResponseInterface {
        $this->cache->clean([\Nette\Caching\Cache::All => true]);
        return $this->respond(['status' => 'ok']);
    }

    public function clearDi() : ResponseInterface {
        $php = glob(TMP_DIR.'*.php');
        $phpLock = glob(TMP_DIR.'*.php.lock');
        $di = glob(TMP_DIR.'di/*');
        $files = array_merge(
          $php === false ? [] : $php,
          $phpLock === false ? [] : $phpLock,
          $di === false ? [] : $di,
        );
        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }

    public function clearModels() : ResponseInterface {
        $files = glob(TMP_DIR.'models/*');
        $deleted = 0;
        if ($files !== false) {
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }

    public function clearConfig() : ResponseInterface {
        $files = glob(TMP_DIR.'*.cache');
        $deleted = 0;
        if ($files !== false) {
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }

    public function clearResults() : ResponseInterface {
        $results = glob(TMP_DIR.'results/*');
        $caches = glob(TMP_DIR.'resultCaches/*');
        if ($results === false) {
            $results = [];
        }
        if ($caches === false) {
            $caches = [];
        }
        $files = array_merge($results, $caches);
        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }

    public function clearLatte() : ResponseInterface {
        $files = glob(TMP_DIR.'latte/*');
        $deleted = 0;
        if ($files !== false) {
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $this->respond(['status' => 'ok', 'deleted' => $deleted, 'total' => count($files)]);
    }
}
