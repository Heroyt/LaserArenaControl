<?php

namespace App\Services;

use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use RuntimeException;

/**
 * A service class for working with app versions - using git
 */
class VersionService
{
    private string $currentVersion;
    /** @var string[] */
    private array $availableVersions;

    private Logger $logger;

    /**
     * @return string returns 'dev' if no version is found
     */
    public function getLastAvailableVersion(): string {
        $versions = $this->getAvailableVersions();
        if (empty($versions)) {
            return 'dev';
        }
        return end($versions);
    }

    /**
     * @return string[] Versions sorted in ascending order
     */
    public function getAvailableVersions(): array {
        if (!isset($this->availableVersions)) {
            $this->execCommand('git fetch --all --tags');
            $versions = $this->execCommand('git tag -l');
            $this->availableVersions = [];
            // Filter all tags to include only the "version" tags
            foreach ($versions as $version) {
                if (preg_match('/v\d+(?:\.\d+)*/', $version) === 1) {
                    $this->availableVersions[] = $version;
                }
            }
            // Sort all versions in ascending order
            usort($this->availableVersions, 'version_compare');
        }
        return $this->availableVersions;
    }

    /**
     * @param string $command
     *
     * @return string[] All lines got from the command
     */
    private function execCommand(string $command): array {
        if (!str_contains($command, '2>&1')) {
            $command .= ' 2>&1'; // Add stderr redirect to stdout
        }
        /** @var string|false $out */
        $out = exec($command, $output, $returnCode);
        if ($out === false || $returnCode !== 0) {
            try {
                $this->getLogger()->error('Running command `' . $command . '` failed.', ['code' => $returnCode, 'output' => $output]);
            } catch (DirectoryCreationException) {
            }
            throw new RuntimeException('Failed to run command');
        }
        return $output;
    }

    /**
     * @return Logger
     * @throws DirectoryCreationException
     */
    private function getLogger(): Logger {
        if (!isset($this->logger)) {
            $this->logger = new Logger(LOG_DIR . 'services/', 'version');
        }
        return $this->logger;
    }

    public function getCurrentVersion(): string {
        if (!isset($this->currentVersion)) {
            $lines = $this->execCommand('git branch --show-current');
            $branch = 'master';
            if (!empty($lines[0])) {
                $branch = $lines[0];
            }
            $this->currentVersion = 'dev';
            if (preg_match('/v\d+(?:\.\d+)*/', $branch) === 1) {
                $this->currentVersion = $branch;
            }
        }
        return $this->currentVersion;
    }
}
