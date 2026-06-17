<?php

declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\DataObjects\Import\ResultFileScanDecision;
use App\Services\ResultsDirectoryScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportStateCommand extends Command
{
    public function __construct(
        private readonly ResultsDirectoryScanner $scanner,
    ) {
        parent::__construct('games:import-state');
        $this->setDescription(self::getDefaultDescription() ?? 'Show result import state and scanner decisions.');
    }

    public static function getDefaultName(): ?string {
        return 'games:import-state';
    }

    public static function getDefaultDescription(): ?string {
        return 'Show result import state and scanner decisions for a result file or directory.';
    }

    protected function configure(): void {
        $this->addArgument('path', InputArgument::REQUIRED, 'Result file or directory.');
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Show decision with force/all scan semantics.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $path = $input->getArgument('path');
        if ( ! is_string($path) || $path === '') {
            $output->writeln('<error>Path is required.</error>');
            return self::INVALID;
        }

        $files = $this->resolveFiles($path);
        if ($files === []) {
            $output->writeln('<error>No result files found.</error>');
            return self::FAILURE;
        }

        $all = (bool)$input->getOption('all');
        $table = new Table($output);
        $table->setHeaders([
            'Path',
            'Action',
            'Reason',
            'Status',
            'Queued age',
            'Processing age',
            'Seen',
            'Processed',
            'Last event',
            'Last error',
        ]);

        foreach ($files as $file) {
            $table->addRow($this->formatDecision($this->scanner->describeFileDecision($file, $all)));
        }

        $table->render();
        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveFiles(string $path): array {
        if (is_file($path)) {
            return [$path];
        }

        if ( ! is_dir($path)) {
            return [];
        }

        $files = glob(trailingSlashIt($path) . '*.game');
        if ($files === false) {
            return [];
        }

        sort($files);
        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * @return list<string>
     */
    private function formatDecision(ResultFileScanDecision $decision): array {
        return [
            $decision->path,
            $decision->action->value,
            $decision->reason,
            $decision->status === null ? '-' : $decision->status->value,
            $this->age($decision->queuedAt),
            $this->age($decision->processingStartedAt),
            $this->formatVersion($decision->seenVersion, $decision->seenHash),
            $this->formatVersion($decision->processedVersion, $decision->processedHash),
            $decision->lastEvent ?? '-',
            $decision->lastError === null || $decision->lastError === '' ? '-' : mb_substr($decision->lastError, 0, 80),
        ];
    }

    private function age(?\DateTimeInterface $time): string {
        if ($time === null) {
            return '-';
        }

        $seconds = max(0, time() - $time->getTimestamp());
        return $seconds . 's';
    }

    private function formatVersion(?string $version, ?string $hash): string {
        if ($version === null) {
            return '-';
        }

        return substr($version, 0, 8) . ' / ' . ($hash === null ? '-' : substr($hash, 0, 8));
    }
}
