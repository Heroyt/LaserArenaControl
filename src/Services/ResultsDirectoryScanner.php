<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\DataObjects\Import\QueuedResultFileImport;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultsScanError;
use App\DataObjects\Import\ResultsScanResult;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use DateTimeImmutable;
use Lsr\Lg\Results\AbstractResultsParser;
use Nette\DI\MissingServiceException;
use RuntimeException;
use Throwable;

readonly class ResultsDirectoryScanner
{
    public function __construct(
        private ResultFileVersionFactory        $versionFactory,
        private ResultFileImportStateRepository $stateRepository,
    )
    {
    }

    /**
     * @param non-empty-string $dir
     */
    public function scan(
        string $dir,
        bool   $all = false,
        int    $limit = 0,
        bool   $includeContent = false,
        int    $maxContentBytes = 65536,
    ): ResultsScanResult
    {
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new RuntimeException('Results directory does not exist or is not readable: ' . $dir);
        }

        $dir = trailingSlashIt($dir);
        $seen = 0;
        $queued = 0;
        $unchanged = 0;
        $invalid = 0;
        $errors = [];
        $queuedFiles = [];
        $processedFiles = [];
        $queuedAt = new DateTimeImmutable();

        foreach (GameFactory::getSupportedSystems() as $system) {
            if ($limit > 0 && $seen >= $limit) {
                break;
            }

            try {
                /**
                 * @var AbstractResultsParser<Game> $parser
                 * @phpstan-ignore missingType.generics
                 */
                $parser = App::getService('result.parser.' . $system);
            } catch (MissingServiceException $e) {
                $errors[] = new ResultsScanError($e->getMessage(), system: $system);
                continue;
            }

            $files = glob($dir . $parser::getFileGlob());
            if ($files === false) {
                $errors[] = new ResultsScanError('Failed to read result file glob.', $dir, $system);
                continue;
            }

            foreach ($files as $file) {
                if ($limit > 0 && $seen >= $limit) {
                    break;
                }
                if (isset($processedFiles[$file])) {
                    continue;
                }
                $processedFiles[$file] = true;

                if (str_ends_with($file, '0000.game') || !$parser::checkFile($file)) {
                    $invalid++;
                    continue;
                }

                try {
                    $version = $this->versionFactory->fromFile($file);
                    $state = $this->stateRepository->findByPathHash($version->pathHash);
                    $seen++;

                    if (
                        !$all
                        && $state !== null
                        && $state->seenVersion === $version->version
                        && $state->status !== ResultFileImportStatus::FAILED
                    ) {
                        $unchanged++;
                        continue;
                    }

                    $this->stateRepository->saveSeen(
                        $version,
                        $system,
                        ResultFileImportStatus::QUEUED,
                        $queuedAt,
                    );
                    $content = null;
                    if ($includeContent && $version->size <= $maxContentBytes) {
                        $content = file_get_contents($file);
                        if ($content === false) {
                            throw new RuntimeException('Failed to read result file content: ' . $file);
                        }
                    }

                    $queuedFiles[] = QueuedResultFileImport::fromVersion($version, $system, $content);
                    $queued++;
                } catch (Throwable $e) {
                    $errors[] = new ResultsScanError($e->getMessage(), $file, $system);
                }
            }
        }

        return new ResultsScanResult(
            dir: $dir,
            seen: $seen,
            queued: $queued,
            unchanged: $unchanged,
            invalid: $invalid,
            queuedFiles: $queuedFiles,
            errors: $errors,
        );
    }
}
