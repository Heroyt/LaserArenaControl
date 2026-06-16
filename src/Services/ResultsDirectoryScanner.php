<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\DataObjects\Import\QueuedResultFileImport;
use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultFileScanAction;
use App\DataObjects\Import\ResultFileScanDecision;
use App\DataObjects\Import\ResultFileVersion;
use App\DataObjects\Import\ResultsScanError;
use App\DataObjects\Import\ResultsScanResult;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use DateTimeImmutable;
use Lsr\Core\Config;
use Lsr\Lg\Results\AbstractResultsParser;
use Nette\DI\MissingServiceException;
use RuntimeException;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;

readonly class ResultsDirectoryScanner
{
    private int $processingTtlSeconds;
    private int $queuedTtlSeconds;

    public function __construct(
        private ResultFileVersionFactory        $versionFactory,
        private ResultFileImportStateRepository $stateRepository,
        Config                                  $config,
        private ?Metrics                        $metrics = null,
    ) {
        $this->processingTtlSeconds = (int)($config->getConfig('ENV')['RESULT_IMPORT_PROCESSING_TTL'] ?? 300);
        $this->queuedTtlSeconds = (int)($config->getConfig('ENV')['RESULT_IMPORT_QUEUED_TTL'] ?? 120);
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
    ): ResultsScanResult {
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
        $decisions = [];
        $processedFiles = [];
        $candidateFiles = [];
        $queuedAt = new DateTimeImmutable();

        $supportedSystems = GameFactory::getSupportedSystems();
        foreach ($supportedSystems as $system) {
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
                $candidateFiles[$file] = true;
                if ($limit > 0 && $seen >= $limit) {
                    break;
                }
                if (isset($processedFiles[$file])) {
                    continue;
                }

                // LaserMaxx writes 0000.game as the prepared game load input, not a result output file.
                if (str_ends_with($file, '0000.game')) {
                    $processedFiles[$file] = true;
                    $invalid++;
                    $decision = new ResultFileScanDecision(
                        $file,
                        null,
                        ResultFileScanAction::INVALID,
                        'invalid-prepared-load-file',
                    );
                    $decisions[] = $decision;
                    $this->recordDecision($decision, $system);
                    $errors[] = new ResultsScanError(
                        'Skipping file with invalid name ending with 0000.game',
                        $file,
                        $system
                    );
                    continue;
                }

                if (!$parser::checkFile($file)) {
                    continue;
                }

                $processedFiles[$file] = true;
                try {
                    $version = $this->versionFactory->fromFile($file);
                    $state = $this->stateRepository->findByPathHash($version->pathHash);
                    $decision = $this->decideForVersion($version, $state, $all, $queuedAt);
                    $decisions[] = $decision;
                    $this->recordDecision($decision, $system);
                    $seen++;

                    if ($decision->action === ResultFileScanAction::SKIP) {
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

        foreach (array_keys($candidateFiles) as $file) {
            if (isset($processedFiles[$file])) {
                continue;
            }
            $invalid++;
            $decision = new ResultFileScanDecision(
                $file,
                null,
                ResultFileScanAction::INVALID,
                'invalid-no-parser',
            );
            $decisions[] = $decision;
            $this->recordDecision($decision, 'unknown');
            $errors[] = new ResultsScanError('Skipping file because no enabled parser accepted its content', $file);
        }

        return new ResultsScanResult(
            dir: $dir,
            seen: $seen,
            queued: $queued,
            unchanged: $unchanged,
            invalid: $invalid,
            queuedFiles: $queuedFiles,
            errors: $errors,
            decisions: $decisions,
        );
    }

    /**
     * @param non-empty-string $file
     */
    public function scanFile(
        string $file,
        bool   $all = false,
        bool   $includeContent = false,
        int    $maxContentBytes = 65536,
    ): ResultsScanResult {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Result file does not exist or is not readable: ' . $file);
        }

        $dir = trailingSlashIt(dirname($file));
        $queuedAt = new DateTimeImmutable();
        $errors = [];

        // LaserMaxx writes 0000.game as the prepared game load input, not a result output file.
        if (str_ends_with($file, '0000.game')) {
            return new ResultsScanResult(
                dir: $dir,
                seen: 0,
                queued: 0,
                unchanged: 0,
                invalid: 1,
                errors: [
                    new ResultsScanError(
                        'Skipping file with invalid name ending with 0000.game',
                        $file
                    ),
                ],
                decisions: [
                    new ResultFileScanDecision(
                        $file,
                        null,
                        ResultFileScanAction::INVALID,
                        'invalid-prepared-load-file',
                    ),
                ],
            );
        }

        $supportedSystems = GameFactory::getSupportedSystems();
        foreach ($supportedSystems as $system) {
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

            if (!$parser::checkFile($file)) {
                continue;
            }

            try {
                $version = $this->versionFactory->fromFile($file);
                $state = $this->stateRepository->findByPathHash($version->pathHash);
                $decision = $this->decideForVersion($version, $state, $all, $queuedAt);
                $this->recordDecision($decision, $system);

                if ($decision->action === ResultFileScanAction::SKIP) {
                    return new ResultsScanResult(
                        dir: $dir,
                        seen: 1,
                        queued: 0,
                        unchanged: 1,
                        invalid: 0,
                        errors: $errors,
                        decisions: [$decision],
                    );
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

                return new ResultsScanResult(
                    dir: $dir,
                    seen: 1,
                    queued: 1,
                    unchanged: 0,
                    invalid: 0,
                    queuedFiles: [QueuedResultFileImport::fromVersion($version, $system, $content)],
                    errors: $errors,
                    decisions: [$decision],
                );
            } catch (Throwable $e) {
                return new ResultsScanResult(
                    dir: $dir,
                    seen: 0,
                    queued: 0,
                    unchanged: 0,
                    invalid: 1,
                    errors: [
                        ...$errors,
                        new ResultsScanError($e->getMessage(), $file, $system),
                    ],
                    decisions: [
                        new ResultFileScanDecision(
                            $file,
                            null,
                            ResultFileScanAction::INVALID,
                            'invalid-metadata-error',
                            lastError: $e->getMessage(),
                        ),
                    ],
                );
            }
        }

        return new ResultsScanResult(
            dir: $dir,
            seen: 0,
            queued: 0,
            unchanged: 0,
            invalid: 1,
            errors: [
                ...$errors,
                new ResultsScanError('Skipping file because no enabled parser accepted its content', $file),
            ],
            decisions: [
                new ResultFileScanDecision(
                    $file,
                    null,
                    ResultFileScanAction::INVALID,
                    'invalid-no-parser',
                ),
            ],
        );
    }

    /**
     * Describe how the scanner would treat a currently readable file based on its import state.
     */
    public function describeFileDecision(
        string             $file,
        bool               $all = false,
        ?DateTimeImmutable $now = null,
    ): ResultFileScanDecision {
        try {
            $version = $this->versionFactory->fromFile($file);
            $state = $this->stateRepository->findByPathHash($version->pathHash);
        } catch (Throwable $e) {
            return new ResultFileScanDecision(
                $file,
                null,
                ResultFileScanAction::INVALID,
                'invalid-metadata-error',
                lastError: $e->getMessage(),
            );
        }

        return $this->decideForVersion($version, $state, $all, $now ?? new DateTimeImmutable());
    }

    private function decideForVersion(
        ResultFileVersion       $version,
        ?ResultFileImportState  $state,
        bool                    $all,
        DateTimeImmutable       $now,
    ): ResultFileScanDecision {
        if ($all) {
            return $this->decision($version, $state, ResultFileScanAction::QUEUE, 'forced-requeued');
        }

        if ($state === null) {
            return $this->decision($version, null, ResultFileScanAction::QUEUE, 'new-file');
        }

        if ($state->seenVersion !== $version->version) {
            return $this->decision($version, $state, ResultFileScanAction::QUEUE, 'changed-version-requeued');
        }

        if ($this->shouldSkipSameVersion($state, $now)) {
            return $this->decision($version, $state, ResultFileScanAction::SKIP, $this->sameVersionSkipReason($state));
        }

        return $this->decision($version, $state, ResultFileScanAction::QUEUE, $this->sameVersionRequeueReason($state, $now));
    }

    private function sameVersionSkipReason(ResultFileImportState $state): string {
        return match ($state->status) {
            ResultFileImportStatus::IMPORTED => 'unchanged-imported',
            ResultFileImportStatus::SKIPPED, ResultFileImportStatus::STALE => 'unchanged-skipped',
            ResultFileImportStatus::QUEUED => 'queued-fresh',
            ResultFileImportStatus::PROCESSING => 'processing-fresh',
            ResultFileImportStatus::LOADED => 'active-loaded-fresh',
            ResultFileImportStatus::STARTED => 'active-started-fresh',
            default => 'unchanged-skipped',
        };
    }

    private function sameVersionRequeueReason(ResultFileImportState $state, DateTimeImmutable $now): string {
        if ($this->isProcessingExpired($state, $now)) {
            return 'processing-expired-requeued';
        }

        return match ($state->status) {
            ResultFileImportStatus::QUEUED => 'queued-expired-requeued',
            ResultFileImportStatus::SEEN => 'seen-unprocessed-requeued',
            ResultFileImportStatus::LOADED => 'active-loaded-requeued',
            ResultFileImportStatus::STARTED => 'active-started-requeued',
            ResultFileImportStatus::FAILED => 'failed-requeued',
            default => 'changed-version-requeued',
        };
    }

    private function decision(
        ResultFileVersion      $version,
        ?ResultFileImportState $state,
        ResultFileScanAction   $action,
        string                 $reason,
    ): ResultFileScanDecision {
        return new ResultFileScanDecision(
            path: $version->path,
            pathHash: $version->pathHash,
            action: $action,
            reason: $reason,
            status: $state?->status,
            seenVersion: $state === null ? $version->version : $state->seenVersion,
            processedVersion: $state?->processedVersion,
            seenHash: $state === null ? $version->contentHash : $state->seenHash,
            processedHash: $state?->processedHash,
            queuedAt: $state?->queuedAt,
            processingStartedAt: $state?->processingStartedAt,
            processedAt: $state?->processedAt,
            lastEvent: $state?->lastEvent,
            lastError: $state?->lastError,
        );
    }

    private function recordDecision(ResultFileScanDecision $decision, string $system): void {
        if ($this->metrics === null) {
            return;
        }

        try {
            /** @var list<non-empty-string> $labels */
            $labels = [
                $this->metricLabel($system),
                $decision->action->value,
                $this->metricLabel($decision->reason),
                $decision->status === null ? 'none' : $decision->status->value,
            ];
            $this->metrics->add(
                'result_file_scan_decisions_total',
                1,
                $labels,
            );
        } catch (Throwable) {
        }
    }

    /**
     * @return non-empty-string
     */
    private function metricLabel(string $value): string {
        return $value === '' ? 'unknown' : $value;
    }

    private function isProcessingExpired(?ResultFileImportState $state, DateTimeImmutable $now): bool {
        if (
            $state === null
            || $state->status !== ResultFileImportStatus::PROCESSING
            || $this->processingTtlSeconds <= 0
        ) {
            return false;
        }

        if ($state->processingStartedAt === null) {
            return true;
        }

        return $state->processingStartedAt->getTimestamp()
            <= ($now->getTimestamp() - $this->processingTtlSeconds);
    }

    private function shouldSkipSameVersion(ResultFileImportState $state, DateTimeImmutable $now): bool {
        if ($state->status === ResultFileImportStatus::FAILED) {
            return false;
        }

        if ($this->isProcessingExpired($state, $now) || $this->isQueuedExpired($state, $now)) {
            return false;
        }

        return true;
    }

    private function isQueuedExpired(?ResultFileImportState $state, DateTimeImmutable $now): bool {
        if (
            $state === null
            || $state->processedVersion !== null
            || $this->queuedTtlSeconds <= 0
            || !in_array(
                $state->status,
                [
                    ResultFileImportStatus::SEEN,
                    ResultFileImportStatus::QUEUED,
                    ResultFileImportStatus::LOADED,
                    ResultFileImportStatus::STARTED,
                ],
                true
            )
        ) {
            return false;
        }

        if ($state->queuedAt === null) {
            return true;
        }

        return $state->queuedAt->getTimestamp() <= ($now->getTimestamp() - $this->queuedTtlSeconds);
    }
}
