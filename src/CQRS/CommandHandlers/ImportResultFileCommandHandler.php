<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\Core\App;
use App\CQRS\Commands\ImportResultFileCommand;
use App\DataObjects\Import\ImportResultFileCommandResult;
use App\DataObjects\Import\ResultFileImportResult;
use App\DataObjects\Import\ResultFileImportState;
use App\DataObjects\Import\ResultFileImportStatus;
use App\Services\ResultFileImporter;
use App\Services\ResultFileImportFinalizer;
use App\Services\ResultFileImportStateRepository;
use App\Services\ResultFileVersionFactory;
use DateTimeImmutable;
use Lsr\Core\Config;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Logging\Logger;
use Nette\DI\MissingServiceException;
use RuntimeException;
use Spiral\RoadRunner\Metrics\Metrics;
use Symfony\Component\Lock\LockFactory;
use Throwable;

readonly class ImportResultFileCommandHandler implements CommandHandlerInterface
{
    private const int IMPORT_LOCK_TTL_SECONDS = 60;
    private int $gameLoadedTime;

    public function __construct(
        private ResultFileImportStateRepository $stateRepository,
        private ResultFileVersionFactory        $versionFactory,
        private ResultFileImporter              $importer,
        private ResultFileImportFinalizer       $finalizer,
        private LockFactory                     $lockFactory,
        private Metrics $metrics,
        Config                                  $config,
    )
    {
        $this->gameLoadedTime = (int)($config->getConfig('ENV')['GAME_LOADED_TIME'] ?? 300);
    }

    /**
     * @param ImportResultFileCommand $command
     */
    public function handle(CommandInterface $command): ImportResultFileCommandResult
    {
        $version = $command->toVersion();
        $logger = new Logger(LOG_DIR . 'results/', 'import-command');
        $startedAt = microtime(true);
        $lock = $this->lockFactory->createLock(
            'result-file-import-' . $command->pathHash,
            ttl: self::IMPORT_LOCK_TTL_SECONDS
        );

        if (!$lock->acquire(false)) {
            return $this->recordMetrics(
                $command,
                new ImportResultFileCommandResult(
                    $command->path,
                    $command->version,
                    ResultFileImportStatus::SKIPPED,
                    event: 'locked',
                ),
                $startedAt,
            );
        }

        try {
            $state = $this->stateRepository->findByPathHash($command->pathHash);
            if ($state === null || $state->seenVersion !== $command->version) {
                return $this->recordMetrics($command, $this->stale($command, 'stale', $state), $startedAt);
            }

            if ($state->processedVersion === $command->version) {
                return $this->recordMetrics(
                    $command,
                    new ImportResultFileCommandResult(
                        $command->path,
                        $command->version,
                        ResultFileImportStatus::SKIPPED,
                        event: 'already-processed',
                    ),
                    $startedAt,
                );
            }

            if ($command->content !== null) {
                $payloadError = $this->validateInlineContent($command);
                if ($payloadError !== null) {
                    $this->stateRepository->markFailed($version, $payloadError);
                    return $this->recordMetrics(
                        $command,
                        new ImportResultFileCommandResult(
                            $command->path,
                            $command->version,
                            ResultFileImportStatus::FAILED,
                            error: $payloadError,
                        ),
                        $startedAt,
                    );
                }
            } else {
                $currentVersion = $this->versionFactory->fromFile($command->path);
                if ($currentVersion->version !== $command->version) {
                    return $this->recordMetrics($command, $this->stale($command, 'changed-on-disk'), $startedAt);
                }
            }

            $this->guardTimeout($startedAt, $command->timeoutSeconds);
            if (!$this->stateRepository->markProcessing($version, new DateTimeImmutable())) {
                return $this->recordMetrics($command, $this->stale($command, 'stale'), $startedAt);
            }

            $lock->refresh(self::IMPORT_LOCK_TTL_SECONDS);
            $parser = $this->getParser($command->system);
            if (
                $command->content === null
                    ? !$parser::checkFile($command->path)
                    : !$parser::checkFile($command->path, $command->content)
            ) {
                $this->stateRepository->markFailed($version, 'Game file cannot be parsed: ' . $command->path);
                return $this->recordMetrics(
                    $command,
                    new ImportResultFileCommandResult(
                        $command->path,
                        $command->version,
                        ResultFileImportStatus::FAILED,
                        error: 'Game file cannot be parsed.',
                    ),
                    $startedAt,
                );
            }

            $this->guardTimeout($startedAt, $command->timeoutSeconds);
            $parseStartedAt = microtime(true);
            $game = $command->content === null
                ? $this->importer->parse(
                    $parser,
                    $command->system,
                    $command->path,
                    $logger,
                )
                : $this->importer->parseContent(
                    $parser,
                    $command->system,
                    $command->path,
                    $command->content,
                    $command->mtime,
                    $logger,
                );
            $this->setMetric(
                'result_file_import_parse_time',
                (microtime(true) - $parseStartedAt) * 1000,
                [$command->system, $command->pathHash],
            );
            $this->guardTimeout($startedAt, $command->timeoutSeconds);

            $state = $this->stateRepository->findByPathHash($command->pathHash);
            if ($state === null || $state->seenVersion !== $command->version) {
                return $this->recordMetrics($command, $this->stale($command, 'stale-after-import', $state), $startedAt);
            }

            $saveStartedAt = microtime(true);
            $result = $this->importer->importParsed(
                $game,
                $command->system,
                $command->path,
                time(),
                $this->gameLoadedTime,
                $logger,
            );
            $this->setMetric(
                'result_file_import_save_time',
                (microtime(true) - $saveStartedAt) * 1000,
                [$command->system, $command->pathHash],
            );
            $this->guardTimeout($startedAt, $command->timeoutSeconds);

            return $this->recordMetrics($command, $this->complete($command, $result, $logger), $startedAt);
        } catch (Throwable $e) {
            try {
                $this->stateRepository->markFailed($version, $e->getMessage());
            } catch (Throwable) {
            }
            $logger->exception($e);
            return $this->recordMetrics(
                $command,
                new ImportResultFileCommandResult(
                    $command->path,
                    $command->version,
                    ResultFileImportStatus::FAILED,
                    error: $e->getMessage(),
                ),
                $startedAt,
            );
        } finally {
            $lock->release();
        }
    }

    private function stale(
        ImportResultFileCommand $command,
        string                  $event,
        ?ResultFileImportState  $state = null,
    ): ImportResultFileCommandResult
    {
        try {
            $this->stateRepository->markStale($command->toVersion(), new DateTimeImmutable());
        } catch (Throwable) {
        }

        return new ImportResultFileCommandResult(
            $command->path,
            $command->version,
            ResultFileImportStatus::STALE,
            event: $event,
            error: $state === null
                ? 'No import state found for path hash ' . $command->pathHash . '.'
                : 'Import state version mismatch. queued=' . $command->version .
                ' stored=' . $state->seenVersion .
                ' status=' . $state->status->value,
        );
    }

    private function guardTimeout(float $startedAt, int $timeoutSeconds): void
    {
        if ($timeoutSeconds > 0 && microtime(true) - $startedAt > $timeoutSeconds) {
            throw new RuntimeException('Import timed out.');
        }
    }

    private function recordMetrics(
        ImportResultFileCommand       $command,
        ImportResultFileCommandResult $result,
        float                         $startedAt,
    ): ImportResultFileCommandResult
    {
        $event = $result->event ?? 'none';
        $this->addMetric(
            'result_file_imports_total',
            1,
            [$command->system, $result->status->value, $event],
        );
        $this->setMetric(
            'result_file_import_time',
            (microtime(true) - $startedAt) * 1000,
            [$command->system, $result->status->value, $event, $command->pathHash],
        );

        return $result;
    }

    /**
     * @param non-empty-string $name
     * @param string[] $labels
     */
    private function addMetric(string $name, int|float $value, array $labels = []): void
    {
        try {
            $this->metrics->add($name, $value, $this->normalizeMetricLabels($labels));
        } catch (Throwable) {
        }
    }

    /**
     * @param non-empty-string $name
     * @param string[] $labels
     */
    private function setMetric(string $name, int|float $value, array $labels = []): void
    {
        try {
            $this->metrics->set($name, $value, $this->normalizeMetricLabels($labels));
        } catch (Throwable) {
        }
    }

    /**
     * @param string[] $labels
     * @return list<non-empty-string>
     */
    private function normalizeMetricLabels(array $labels): array
    {
        $normalized = [];
        foreach ($labels as $label) {
            $normalized[] = $label === '' ? 'unknown' : $label;
        }

        return $normalized;
    }

    private function validateInlineContent(ImportResultFileCommand $command): ?string
    {
        if ($command->content === null) {
            return null;
        }

        if (strlen($command->content) !== $command->size) {
            return 'Inline content size does not match queued file metadata.';
        }

        if (hash('sha256', $command->content) !== $command->contentHash) {
            return 'Inline content hash does not match queued file metadata.';
        }

        return null;
    }

    /** @phpstan-ignore missingType.generics */
    private function getParser(string $system): AbstractResultsParser
    {
        try {
            $parser = App::getService('result.parser.' . $system);
        } catch (MissingServiceException $e) {
            throw new RuntimeException('No parser for this game system (' . $system . ')', previous: $e);
        }
        if (!$parser instanceof AbstractResultsParser) {
            throw new RuntimeException('No parser for this game system (' . $system . ')');
        }

        return $parser;
    }

    private function complete(
        ImportResultFileCommand $command,
        ResultFileImportResult  $result,
        Logger                  $logger,
    ): ImportResultFileCommandResult
    {
        $version = $command->toVersion();
        $now = new DateTimeImmutable();

        if ($result->imported && $result->game !== null) {
            $this->stateRepository->markImported($version, $now, $result->game->code);
            $this->finalizer->triggerImported(1);
            $this->finalizer->finalize([$result->game], $logger);

            return new ImportResultFileCommandResult(
                $command->path,
                $command->version,
                ResultFileImportStatus::IMPORTED,
                gameCode: $result->game->code,
                event: 'imported',
            );
        }

        if ($result->unfinishedGame !== null) {
            $this->stateRepository->markSkipped($version, $now, $result->unfinishedEvent);
            $this->finalizer->triggerUnfinished($result->unfinishedGame, $result->unfinishedEvent, $logger);
            return new ImportResultFileCommandResult(
                $command->path,
                $command->version,
                ResultFileImportStatus::SKIPPED,
                event: $result->unfinishedEvent,
            );
        }

        if ($result->saveFailed) {
            $this->stateRepository->markFailed($version, 'Failed saving game into DB.');
            return new ImportResultFileCommandResult(
                $command->path,
                $command->version,
                ResultFileImportStatus::FAILED,
                event: 'save-failed',
                error: 'Failed saving game into DB.',
            );
        }

        $event = $result->empty ? 'empty' : 'skipped';
        $this->stateRepository->markSkipped($version, $now, $event);
        return new ImportResultFileCommandResult(
            $command->path,
            $command->version,
            ResultFileImportStatus::SKIPPED,
            event: $event,
        );
    }
}
