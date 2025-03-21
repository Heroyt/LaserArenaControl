<?php

namespace App\Controllers\Api;

use App\Install\Install;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class Updater extends ApiController
{
    /**
     * Combines Updater::pull(), Updater::build() and Updater::install() methods
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function update(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'update');
            $logger->info('Updating LAC - ('.$request->getIp().')');
        } catch (DirectoryCreationException $e) {
            $logger = null;
        }

        /** @var string|false $out */
        $out = exec(
          'git stash push -u 2>&1 && git pull --recurse-submodules 2>&1 && git submodule update --init --recursive --remote 2>&1',
          $output,
          $returnCode
        );
        exec('git stash pop 2>&1', $output2);
        $output = array_merge($output, $output2);

        if ($out === false || $returnCode !== 0) {
            $logger?->warning('Cannot execute command');
            $logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
            $logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
            return $this->respond(
              ['error' => 'Cannot execute git pull', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }

        /** @var string|false $out */
        $out = exec('COMPOSER_HOME=$(pwd) composer build 2>&1', $output, $returnCode);

        if ($out === false || $returnCode !== 0) {
            $logger?->warning('Cannot execute command');
            $logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
            $logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
            return $this->respond(
              ['error' => 'Cannot execute build', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }

        ob_start();
        $success = Install::install();
        /** @var string $output */
        $output = ob_get_clean();

        if (!$success) {
            $logger?->warning('Install failed');
            $logger?->debug($output);
            return $this->respond(['error' => 'Install failed', 'output' => $output], 500);
        }

        return $this->respond(['success' => true, 'output' => $output]);
    }

    /**
     * Install the database changes
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function install(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'update');
            $logger->info('Updating LAC - install ('.$request->getIp().')');
        } catch (DirectoryCreationException $e) {
            $logger = null;
        }

        ob_start();
        $fresh = (int) $request->getPost('fresh', 0);
        $success = Install::install($fresh === 1);
        /** @var string|false $output */
        $output = ob_get_clean();

        if (!$success) {
            $logger?->warning('Install failed');
            $logger?->debug($output !== false ? $output : '');
            return $this->respond(['error' => 'Install failed', 'output' => $output], 500);
        }
        return $this->respond(['success' => true, 'output' => $output]);
    }

    /**
     * Pull changes from remote using an API route
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function pull(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'update');
            $logger->info('Updating LAC - pull ('.$request->getIp().')');
        } catch (DirectoryCreationException $e) {
            $logger = null;
        }

        /** @var string|false $out */
        $out = exec(
          'git stash push -u 2>&1 && git pull --recurse-submodules 2>&1 && git submodule update --init --recursive --remote 2>&1',
          $output,
          $returnCode
        );
        exec('git stash pop 2>&1', $output2);
        $output = array_merge($output, $output2);

        if ($out === false || $returnCode !== 0) {
            $logger?->warning('Cannot execute command');
            $logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
            $logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
            return $this->respond(
              ['error' => 'Cannot execute git pull', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }
        return $this->respond(['success' => true, 'output' => $output]);
    }

    /**
     * Fetch changes from remote using an API route
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function fetch(Request $request) : ResponseInterface {
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
            return $this->respond(
              ['error' => 'Cannot execute git fetch', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }
        return $this->respond(['success' => true, 'output' => $output]);
    }

    /**
     * Get GIT status using an API route
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function status(Request $request) : ResponseInterface {
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
            return $this->respond(
              ['error' => 'Cannot execute git status', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }
        return $this->respond(['success' => true, 'output' => $output]);
    }

    /**
     * Build all assets using an API route
     *
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function build(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'update');
            $logger->info('Updating LAC - build ('.$request->getIp().')');
        } catch (DirectoryCreationException $e) {
            $logger = null;
        }

        if ($request->getGet('npmOnly', false) === false) {
            /** @var string|false $out */
            $out = exec('npm run build 2>&1', $output, $returnCode);
        }
        else {
            if ($request->getGet('composerOnly', false) === false) {
                /** @var string|false $out */
                $out = exec(
                  'COMPOSER_HOME=$(pwd) composer update 2>&1 && COMPOSER_HOME=$(pwd) composer dump-autoload 2>&1',
                  $output,
                  $returnCode
                );
            }
            else {
                /** @var string|false $out */
                $out = exec('COMPOSER_HOME=$(pwd) composer build 2>&1', $output, $returnCode);
            }
        }

        if ($out === false || $returnCode !== 0) {
            $logger?->warning('Cannot execute command');
            $logger?->debug(json_encode($out, JSON_THROW_ON_ERROR));
            $logger?->debug(json_encode($output, JSON_THROW_ON_ERROR));
            return $this->respond(
              ['error' => 'Cannot execute build', 'errorCode' => $returnCode, 'output' => $output],
              500
            );
        }
        return $this->respond(['success' => true]);
    }
}
