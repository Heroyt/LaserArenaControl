<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\CQRS\Commands\ImportResultFileCommand;
use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\DataObjects\Import\ImportResultFileCommandResult;
use App\DataObjects\Import\QueuedResultFileImport;
use App\DataObjects\Import\ResultFileImportStatus;
use App\DataObjects\Import\ResultsScanError;
use App\GameModels\Factory\GameFactory;
use App\Services\ImportService;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Serializer;
use Throwable;

class ImportGameCommand extends Command
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly CommandBus    $commandBus,
        private readonly Serializer    $serializer,
    ) {
        parent::__construct('games:import');
    }

    public static function getDefaultName(): ?string
    {
        return 'games:import';
    }

    public static function getDefaultDescription(): ?string
    {
        return 'Import games from a directory.';
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Import all games in a directory - ignore modification time.'
        );
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Limit games to import.'
        );
        $this->addOption(
            'async',
            null,
            InputOption::VALUE_NONE,
            'Only scan and queue changed files. By default, changed files are imported synchronously.'
        );
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_REQUIRED,
            'Per-file synchronous import timeout in seconds. Use 0 to disable.',
            30
        );
        $this->addOption(
            'isolate',
            null,
            InputOption::VALUE_NONE,
            'Run each synchronous file import in a separate PHP process so crashes do not stop the batch.'
        );
        $this->addOption('worker-import-file', null, InputOption::VALUE_REQUIRED, 'Internal worker payload file.');
        $this->addArgument('directory', InputArgument::REQUIRED, 'Results directory');
        $this->addArgument('game', InputArgument::OPTIONAL, 'Game code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $input->getArgument('directory');
        $gameCode = $input->getArgument('game');
        $limit = (int) $input->getOption('limit');
        $async = (bool)$input->getOption('async');
        $timeout = max(0, (int)$input->getOption('timeout'));
        $isolate = (bool)$input->getOption('isolate');
        $workerImportFile = $input->getOption('worker-import-file');

        if (is_string($workerImportFile) && $workerImportFile !== '') {
            return $this->executeWorkerImport($workerImportFile, $timeout, $output);
        }

        if (!file_exists($dir) || !is_dir($dir)) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) . 'Error: argument must be a valid directory.' . Colors::reset()
            );
            return self::FAILURE;
        }

        /** @var non-empty-string $dir */
        $dir = trailingslashit($dir);
        $output->writeln(
            sprintf(
                '<info>Importing results from %s (%s)</info>',
                $dir,
                $async ? 'async queue mode' : 'synchronous mode' . ($isolate ? ', isolated' : '')
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );

        if (!empty($gameCode)) {
            $output->writeln(
                sprintf('<info>Importing single game %s</info>', $gameCode),
                OutputInterface::VERBOSITY_VERBOSE
            );
            try {
                $game = GameFactory::getByCode($gameCode);
            } catch (Throwable $e) {
                $output->writeln(
                    '<error>Error: Game not found - ' . $e->getMessage() . '.</error>'
                );
                return self::FAILURE;
            }

            if (!isset($game)) {
                $output->writeln(
                    '<error>Error: Game not found.</error>'
                );
                return self::FAILURE;
            }

            $response = $this->importService->importGame($game, $dir);

            if ($response instanceof ErrorResponse) {
                $output->writeln('<error>' . $response->title . '</error>');
                if (!empty($response->values)) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
                    $output->writeln($this->serializer->serialize($response->values, 'json'));
                    $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
                }
                return self::FAILURE;
            }

            $output->writeln('<info>Game imported.</info>');
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln($this->serializer->serialize($response->values, 'json'));
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            return self::SUCCESS;
        }

        try {
            $output->writeln('<info>Scanning result directory...</info>', OutputInterface::VERBOSITY_VERBOSE);
            $response = $this->commandBus->dispatch(
                new ScanResultsDirectoryCommand(
                    $dir,
                    (bool)$input->getOption('all'),
                    $limit,
                    queueImports: $async,
                )
            );
            $this->writeScanDetails($output, $response->queuedFiles, $response->errors);
            $importResults = [];
            if (!$async) {
                $totalQueued = count($response->queuedFiles);
                foreach ($response->queuedFiles as $index => $queuedFile) {
                    $startedAt = microtime(true);
                    $output->writeln(
                        sprintf(
                            '<comment>[%d/%d] Importing %s (%s, %d B, timeout=%ss)</comment>',
                            $index + 1,
                            $totalQueued,
                            $queuedFile->path,
                            $queuedFile->system,
                            $queuedFile->size,
                            $timeout === 0 ? 'off' : (string)$timeout
                        ),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                    $result = $isolate
                        ? $this->dispatchIsolatedImport($queuedFile, $timeout)
                        : $this->commandBus->dispatch(ImportResultFileCommand::fromQueuedFile($queuedFile, $timeout));
                    $importResults[] = $result;
                    $this->writeImportResult($output, $result, microtime(true) - $startedAt);
                }
            }
        } catch (Throwable $e) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) .
                $e->getMessage() .
                Colors::reset()
            );
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln($e->getTraceAsString());
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            return self::FAILURE;
        }

        if (!$async) {
            $this->writeSynchronousImportSummary($output, $response->seen, $response->unchanged, $response->invalid, $importResults);
            if ($response->errors !== []) {
                $output->writeln('<comment>Scan completed with ' . count($response->errors) . ' non-fatal errors.</comment>');
            }
            return self::SUCCESS;
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) .
            'Queued: ' . $response->queued . '/' . $response->seen .
            ' changed result files. Unchanged: ' . $response->unchanged . '. Invalid: ' . $response->invalid . '.' .
            Colors::reset()
        );
        if ($response->errors !== []) {
            $output->writeln('<comment>Scan completed with ' . count($response->errors) . ' non-fatal errors.</comment>');
        }
        return self::SUCCESS;
    }

    private function executeWorkerImport(string $payloadFile, int $timeout, OutputInterface $output): int
    {
        $payload = file_get_contents($payloadFile);
        if ($payload === false) {
            $output->writeln('<error>Failed to read worker import payload.</error>');
            return self::FAILURE;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            $output->writeln('<error>Invalid worker import payload.</error>');
            return self::FAILURE;
        }

        $queuedFile = new QueuedResultFileImport(
            (string)$data['path'],
            (string)$data['pathHash'],
            (string)$data['system'],
            (int)$data['mtime'],
            (int)$data['size'],
            (string)$data['contentHash'],
            (string)$data['version'],
            isset($data['content']) && is_string($data['content']) ? $data['content'] : null,
        );

        $result = $this->commandBus->dispatch(ImportResultFileCommand::fromQueuedFile($queuedFile, $timeout));
        echo json_encode([
            'path' => $result->path,
            'version' => $result->version,
            'status' => $result->status->value,
            'gameCode' => $result->gameCode,
            'event' => $result->event,
            'error' => $result->error,
        ], JSON_THROW_ON_ERROR);

        return $result->status === ResultFileImportStatus::FAILED ? self::FAILURE : self::SUCCESS;
    }

    private function dispatchIsolatedImport(
        QueuedResultFileImport $queuedFile,
        int                    $timeout,
    ): ImportResultFileCommandResult
    {
        $payloadFile = tempnam(TMP_DIR, 'result-import-');
        if ($payloadFile === false) {
            return new ImportResultFileCommandResult(
                $queuedFile->path,
                $queuedFile->version,
                ResultFileImportStatus::FAILED,
                error: 'Failed to create isolated import payload file.',
            );
        }

        try {
            file_put_contents($payloadFile, json_encode([
                'path' => $queuedFile->path,
                'pathHash' => $queuedFile->pathHash,
                'system' => $queuedFile->system,
                'mtime' => $queuedFile->mtime,
                'size' => $queuedFile->size,
                'contentHash' => $queuedFile->contentHash,
                'version' => $queuedFile->version,
                'content' => $queuedFile->content,
            ], JSON_THROW_ON_ERROR));

            $command = [
                PHP_BINARY,
                'bin/console',
                'games:import',
                $queuedFile->path,
                '--worker-import-file=' . $payloadFile,
                '--timeout=' . $timeout,
                '--no-interaction',
            ];
            if ($timeout > 0 && $this->hasTimeoutCommand()) {
                array_unshift($command, (string)($timeout + 5));
                array_unshift($command, 'timeout');
            }

            $process = new Process($command);
            $process->setTimeout($timeout > 0 ? $timeout + 10 : null);
            $process->run();

            $output = trim($process->getOutput());
            $data = json_decode($output, true);
            if ($process->isSuccessful() && is_array($data)) {
                return new ImportResultFileCommandResult(
                    (string)$data['path'],
                    (string)$data['version'],
                    ResultFileImportStatus::from((string)$data['status']),
                    isset($data['gameCode']) && is_string($data['gameCode']) ? $data['gameCode'] : null,
                    isset($data['event']) && is_string($data['event']) ? $data['event'] : null,
                    isset($data['error']) && is_string($data['error']) ? $data['error'] : null,
                );
            }

            if ($process->getExitCode() === 124) {
                return new ImportResultFileCommandResult(
                    $queuedFile->path,
                    $queuedFile->version,
                    ResultFileImportStatus::FAILED,
                    event: 'isolated-process-timeout',
                    error: 'Isolated import process timed out after ' . ($timeout + 5) . ' seconds.',
                );
            }

            return new ImportResultFileCommandResult(
                $queuedFile->path,
                $queuedFile->version,
                ResultFileImportStatus::FAILED,
                event: 'isolated-process-failed',
                error: trim($process->getErrorOutput() . "\n" . $process->getOutput())
                    ?: 'Isolated import process failed with exit code ' . $process->getExitCode() . '.',
            );
        } catch (ProcessTimedOutException $e) {
            return new ImportResultFileCommandResult(
                $queuedFile->path,
                $queuedFile->version,
                ResultFileImportStatus::FAILED,
                event: 'isolated-process-timeout',
                error: 'Isolated import process timed out after ' . $e->getExceededTimeout() . ' seconds.',
            );
        } catch (Throwable $e) {
            return new ImportResultFileCommandResult(
                $queuedFile->path,
                $queuedFile->version,
                ResultFileImportStatus::FAILED,
                event: 'isolated-process-failed',
                error: $e->getMessage(),
            );
        } finally {
            if (is_file($payloadFile)) {
                unlink($payloadFile);
            }
        }
    }

    private function hasTimeoutCommand(): bool
    {
        static $hasTimeout = null;
        if ($hasTimeout !== null) {
            return $hasTimeout;
        }

        $process = new Process(['sh', '-c', 'command -v timeout']);
        $process->setTimeout(2);
        $process->run();
        $hasTimeout = $process->isSuccessful();

        return $hasTimeout;
    }

    /**
     * @param QueuedResultFileImport[] $queuedFiles
     * @param ResultsScanError[] $errors
     */
    private function writeScanDetails(OutputInterface $output, array $queuedFiles, array $errors): void
    {
        $output->writeln(
            sprintf('<info>Scan found %d changed result file(s).</info>', count($queuedFiles)),
            OutputInterface::VERBOSITY_VERBOSE
        );

        foreach ($queuedFiles as $queuedFile) {
            $output->writeln(
                sprintf(
                    '  queued %s system=%s mtime=%d size=%d hash=%s version=%s',
                    $queuedFile->path,
                    $queuedFile->system,
                    $queuedFile->mtime,
                    $queuedFile->size,
                    $queuedFile->contentHash,
                    $queuedFile->version
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
        }

        foreach ($errors as $error) {
            $output->writeln(
                sprintf(
                    '<comment>Scan error: %s%s%s</comment>',
                    $error->message,
                    $error->path !== null ? ' path=' . $error->path : '',
                    $error->system !== null ? ' system=' . $error->system : ''
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
    }

    private function writeImportResult(
        OutputInterface               $output,
        ImportResultFileCommandResult $result,
        float                         $elapsedSeconds,
    ): void
    {
        $message = sprintf(
            '  -> %s %.3fs %s%s%s',
            $result->status->value,
            $elapsedSeconds,
            $result->path,
            $result->event !== null ? ' event=' . $result->event : '',
            $result->gameCode !== null ? ' game=' . $result->gameCode : ''
        );
        if ($result->error !== null) {
            $message .= ' error=' . $result->error;
        }

        $output->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * @param ImportResultFileCommandResult[] $importResults
     */
    private function writeSynchronousImportSummary(
        OutputInterface $output,
        int             $seen,
        int             $unchanged,
        int             $invalid,
        array           $importResults,
    ): void
    {
        $counts = [
            ResultFileImportStatus::IMPORTED->value => 0,
            ResultFileImportStatus::SKIPPED->value => 0,
            ResultFileImportStatus::FAILED->value => 0,
            ResultFileImportStatus::STALE->value => 0,
        ];

        foreach ($importResults as $result) {
            $counts[$result->status->value] = ($counts[$result->status->value] ?? 0) + 1;
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) .
            'Imported: ' . $counts[ResultFileImportStatus::IMPORTED->value] .
            '. Skipped: ' . $counts[ResultFileImportStatus::SKIPPED->value] .
            '. Failed: ' . $counts[ResultFileImportStatus::FAILED->value] .
            '. Stale: ' . $counts[ResultFileImportStatus::STALE->value] .
            '. Seen: ' . $seen .
            '. Unchanged: ' . $unchanged .
            '. Invalid: ' . $invalid . '.' .
            Colors::reset()
        );
    }
}
