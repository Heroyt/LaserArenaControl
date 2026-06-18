<?php

declare(strict_types=1);

namespace App\Cli\Commands\Regression;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Lasermaxx\Player as LasermaxxPlayer;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Services\Predictor\GamePredictionContextFactory;
use App\Services\Predictor\GameResultPredictor;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Dto\PredictionResult;
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Exception\PredictionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @phpstan-type PredictionDetail array{
 *     game:string,
 *     vest:string,
 *     name:string,
 *     target:string,
 *     actual:float|null,
 *     legacy:float|null,
 *     predictorPer15:float|null,
 *     predictorGame:float|null,
 *     model:string,
 *     fallback:string,
 *     status:string
 * }
 * @phpstan-type PredictionSummary array{
 *     target:string,
 *     count:int,
 *     actualSum:float,
 *     legacySum:float,
 *     predictorSum:float,
 *     legacyErrorSum:float,
 *     predictorErrorSum:float,
 *     legacyAbsErrorSum:float,
 *     predictorAbsErrorSum:float,
 *     legacySquaredErrorSum:float,
 *     predictorSquaredErrorSum:float,
 *     failures:int
 * }
 */
final class PredictorDiagnosticsCommand extends Command
{
    /** @var non-empty-list<PredictionTarget> */
    private const array DEFAULT_TARGETS = [
        PredictionTarget::ENEMY_HITS,
        PredictionTarget::ENEMY_DEATHS,
        PredictionTarget::TEAMMATE_HITS,
        PredictionTarget::TEAMMATE_DEATHS,
    ];

    public function __construct(
        private readonly GamePredictionContextFactory $contextFactory,
        private readonly GameResultPredictor $predictor,
    ) {
        parent::__construct('predictor:diagnostics');
    }

    public static function getDefaultName(): string {
        return 'predictor:diagnostics';
    }

    public static function getDefaultDescription(): string {
        return 'Compare predictor output with actual and legacy expected game values.';
    }

    protected function configure(): void {
        $this->setDescription(self::getDefaultDescription());
        $this->addArgument('code', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Game code. Can be repeated.');
        $this->addOption(
            'vest',
            null,
            InputOption::VALUE_REQUIRED,
            'Player vest number. If omitted, all players are evaluated.',
        );
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Prediction target. Can be used more than once.',
        );
        $this->addOption(
            'exclude-empty-results',
            null,
            InputOption::VALUE_NONE,
            'Exclude player results where all selected actual target values are zero or unavailable.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $targetOption = $input->getOption('target');
        $targets = $this->parseTargets(is_array($targetOption) ? array_values($targetOption) : []);

        /** @var list<mixed> $codeArguments */
        $codeArguments = $input->getArgument('code');
        $codes = array_map(static fn (mixed $code): string => (string) $code, $codeArguments);
        $vestOption = $input->getOption('vest');
        $vest = $vestOption === null || $vestOption === '' ? null : (string) $vestOption;
        $excludeEmptyResults = $input->getOption('exclude-empty-results') === true;

        if ($codes === []) {
            $output->writeln('<error>At least one game code is required.</error>');

            return self::FAILURE;
        }

        if (count($codes) === 1) {
            return $this->executeSingleGame($codes[0], $vest, $excludeEmptyResults, $targets, $output);
        }

        return $this->executeMultipleGames($codes, $vest, $excludeEmptyResults, $targets, $output);
    }

    /**
     * @param non-empty-list<PredictionTarget> $targets
     */
    private function executeSingleGame(
        string $code,
        ?string $vest,
        bool $excludeEmptyResults,
        array $targets,
        OutputInterface $output,
    ): int {
        $game = GameFactory::getByCode($code);
        if ($game === null) {
            $output->writeln('<error>Game was not found.</error>');

            return self::FAILURE;
        }

        if ($vest === null) {
            $output->writeln(sprintf('<info>Game %s / all players</info>', $game->code));
            $this->renderGameSummary($output, $game, $targets, null, $excludeEmptyResults);

            return self::SUCCESS;
        }

        $player = $this->findPlayer($game, $vest);
        if ($player === null) {
            $output->writeln('<error>Player vest was not found in the game.</error>');

            return self::FAILURE;
        }

        if ($excludeEmptyResults && $this->hasEmptyActualResults($player, $targets)) {
            $output->writeln(sprintf(
                '<comment>Game %s / vest %s / %s was skipped because all selected actual target values are empty.</comment>',
                $game->code,
                (string) $player->vest,
                $player->name,
            ));

            return self::SUCCESS;
        }

        $context = $this->contextFactory->createForPlayer($player);

        $output->writeln(sprintf(
            '<info>Game %s / vest %s / %s</info>',
            $game->code,
            (string) $player->vest,
            $player->name,
        ));
        $this->renderContext($output, $context);
        $this->renderPredictions($output, $player, $context, $targets);

        return self::SUCCESS;
    }

    /**
     * @param non-empty-list<string> $codes
     * @param non-empty-list<PredictionTarget> $targets
     */
    private function executeMultipleGames(
        array $codes,
        ?string $vest,
        bool $excludeEmptyResults,
        array $targets,
        OutputInterface $output,
    ): int {
        $summaries = $this->initializeSummaries($targets);
        $details = [];
        $processedGames = 0;
        $missingGames = 0;
        $missingPlayers = 0;
        $skippedPlayers = 0;

        $output->writeln(sprintf(
            '<info>Processing %d games / %s%s</info>',
            count($codes),
            $vest === null ? 'all players' : 'vest ' . $vest,
            $excludeEmptyResults ? ' / excluding empty results' : '',
        ));

        $progressBar = new ProgressBar($output, count($codes));
        $progressBar->start();

        foreach ($codes as $code) {
            $game = GameFactory::getByCode($code);
            if ($game === null) {
                $missingGames++;
                $progressBar->advance();

                continue;
            }

            $processedGames++;
            $missingPlayers += $this->collectGamePredictions(
                $game,
                $targets,
                $summaries,
                $details,
                $vest,
                $excludeEmptyResults,
                $skippedPlayers,
            );
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Processed %d/%d games</info>',
            $processedGames,
            count($codes),
        ));

        if ($missingGames > 0) {
            $output->writeln(sprintf('<comment>Missing games: %d</comment>', $missingGames));
        }

        if ($missingPlayers > 0) {
            $output->writeln(sprintf('<comment>Games without matching vest: %d</comment>', $missingPlayers));
        }

        if ($skippedPlayers > 0) {
            $output->writeln(sprintf('<comment>Skipped empty player results: %d</comment>', $skippedPlayers));
        }

        $this->renderSummaryTable($output, $summaries);

        if ($output->isVerbose()) {
            $this->renderDetailTable($output, $details);
        }

        return $processedGames === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @param Game<T, P> $game
     * @return P|null
     */
    private function findPlayer(Game $game, string $vest): ?Player {
        $player = $game->getVestPlayer(is_numeric($vest) ? (int) $vest : $vest);

        return $player;
    }

    /**
     * @param list<mixed> $targetValues
     * @return non-empty-list<PredictionTarget>
     */
    private function parseTargets(array $targetValues): array {
        if ($targetValues === []) {
            return self::DEFAULT_TARGETS;
        }

        $targets = [];
        foreach ($targetValues as $targetValue) {
            $target = PredictionTarget::tryFrom((string) $targetValue);
            if ($target === null) {
                throw new PredictionException('Unknown prediction target: ' . (string) $targetValue);
            }
            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @param Game<T, P> $game
     * @param non-empty-list<PredictionTarget> $targets
     */
    private function renderGameSummary(
        OutputInterface $output,
        Game $game,
        array $targets,
        ?string $vest,
        bool $excludeEmptyResults,
    ): void {
        $summaries = $this->initializeSummaries($targets);
        $details = [];
        $skippedPlayers = 0;

        $this->collectGamePredictions(
            $game,
            $targets,
            $summaries,
            $details,
            $vest,
            $excludeEmptyResults,
            $skippedPlayers,
        );

        if ($skippedPlayers > 0) {
            $output->writeln(sprintf('<comment>Skipped empty player results: %d</comment>', $skippedPlayers));
        }

        $this->renderSummaryTable($output, $summaries);

        if ($output->isVerbose()) {
            $this->renderDetailTable($output, $details);
        }
    }

    /**
     * @template T of Team
     * @template P of Player
     * @param Game<T, P> $game
     * @param non-empty-list<PredictionTarget> $targets
     * @param array<string, PredictionSummary> $summaries
     * @param list<PredictionDetail> $details
     * @return 0|1
     */
    private function collectGamePredictions(
        Game $game,
        array $targets,
        array &$summaries,
        array &$details,
        ?string $vest,
        bool $excludeEmptyResults,
        int &$skippedPlayers,
    ): int {
        if ($vest !== null) {
            $player = $this->findPlayer($game, $vest);
            if ($player === null) {
                return 1;
            }

            if ($excludeEmptyResults && $this->hasEmptyActualResults($player, $targets)) {
                $skippedPlayers++;

                return 0;
            }

            $context = $this->contextFactory->createForPlayer($player);
            foreach ($targets as $target) {
                $detail = $this->predictionDetail($target, $player, $context);
                $details[] = $detail;
                $this->addDetailToSummary($summaries[$target->value], $detail);
            }

            return 0;
        }

        foreach ($game->players as $player) {
            if ($excludeEmptyResults && $this->hasEmptyActualResults($player, $targets)) {
                $skippedPlayers++;

                continue;
            }

            $context = $this->contextFactory->createForPlayer($player);
            foreach ($targets as $target) {
                $detail = $this->predictionDetail($target, $player, $context);
                $details[] = $detail;
                $this->addDetailToSummary($summaries[$target->value], $detail);
            }
        }

        return 0;
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     * @param non-empty-list<PredictionTarget> $targets
     */
    private function hasEmptyActualResults(Player $player, array $targets): bool {
        foreach ($targets as $target) {
            $actual = $this->actualValue($target, $player);
            if ($actual !== null && $actual > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param non-empty-list<PredictionTarget> $targets
     * @return array<string, PredictionSummary>
     */
    private function initializeSummaries(array $targets): array {
        $summaries = [];
        foreach ($targets as $target) {
            $summaries[$target->value] = [
                'target'                    => $target->value,
                'count'                     => 0,
                'actualSum'                 => 0.0,
                'legacySum'                 => 0.0,
                'predictorSum'              => 0.0,
                'legacyErrorSum'            => 0.0,
                'predictorErrorSum'         => 0.0,
                'legacyAbsErrorSum'         => 0.0,
                'predictorAbsErrorSum'      => 0.0,
                'legacySquaredErrorSum'     => 0.0,
                'predictorSquaredErrorSum'  => 0.0,
                'failures'                  => 0,
            ];
        }

        return $summaries;
    }

    /**
     * @param PredictionSummary $summary
     * @param PredictionDetail $detail
     */
    private function addDetailToSummary(array &$summary, array $detail): void {
        if ($detail['status'] !== 'ok') {
            $summary['failures']++;

            return;
        }

        if ($detail['actual'] === null || $detail['legacy'] === null || $detail['predictorGame'] === null) {
            $summary['failures']++;

            return;
        }

        $legacyError = $detail['actual'] - $detail['legacy'];
        $predictorError = $detail['actual'] - $detail['predictorGame'];

        $summary['count']++;
        $summary['actualSum'] += $detail['actual'];
        $summary['legacySum'] += $detail['legacy'];
        $summary['predictorSum'] += $detail['predictorGame'];
        $summary['legacyErrorSum'] += $legacyError;
        $summary['predictorErrorSum'] += $predictorError;
        $summary['legacyAbsErrorSum'] += abs($legacyError);
        $summary['predictorAbsErrorSum'] += abs($predictorError);
        $summary['legacySquaredErrorSum'] += $legacyError ** 2;
        $summary['predictorSquaredErrorSum'] += $predictorError ** 2;
    }

    /**
     * @param array<string, PredictionSummary> $summaries
     */
    private function renderSummaryTable(OutputInterface $output, array $summaries): void {
        $rows = [];
        foreach ($summaries as $summary) {
            $rows[] = $this->summaryRow($summary);
        }

        (new Table($output))
            ->setHeaderTitle('Prediction performance summary')
            ->setHeaders([
                'Target',
                'Rows',
                'Actual avg',
                'Legacy avg',
                'Legacy MAE',
                'Legacy bias',
                'Legacy RMSE',
                'Predictor avg',
                'Predictor MAE',
                'Predictor bias',
                'Predictor RMSE',
                'Failures',
            ])
            ->setRows($rows)
            ->render();
    }

    /**
     * @param PredictionSummary $summary
     * @return non-empty-list<string>
     */
    private function summaryRow(array $summary): array {
        if ($summary['count'] === 0) {
            return [
                $summary['target'],
                '0',
                '-',
                '-',
                '-',
                '-',
                '-',
                '-',
                '-',
                '-',
                '-',
                (string) $summary['failures'],
            ];
        }

        $count = $summary['count'];

        return [
            $summary['target'],
            (string) $count,
            sprintf('%.3f', $summary['actualSum'] / $count),
            sprintf('%.3f', $summary['legacySum'] / $count),
            sprintf('%.3f', $summary['legacyAbsErrorSum'] / $count),
            sprintf('%+.3f', $summary['legacyErrorSum'] / $count),
            sprintf('%.3f', sqrt($summary['legacySquaredErrorSum'] / $count)),
            sprintf('%.3f', $summary['predictorSum'] / $count),
            sprintf('%.3f', $summary['predictorAbsErrorSum'] / $count),
            sprintf('%+.3f', $summary['predictorErrorSum'] / $count),
            sprintf('%.3f', sqrt($summary['predictorSquaredErrorSum'] / $count)),
            (string) $summary['failures'],
        ];
    }

    /**
     * @param list<PredictionDetail> $details
     */
    private function renderDetailTable(OutputInterface $output, array $details): void {
        $rows = [];
        foreach ($details as $detail) {
            $rows[] = [
                $detail['game'],
                $detail['vest'],
                $detail['name'],
                $detail['target'],
                $this->formatNullable($detail['actual']),
                $this->formatValueWithError($detail['legacy'], $detail['actual']),
                $detail['predictorPer15'] === null ? '-' : sprintf('%.3f', $detail['predictorPer15']),
                $this->formatValueWithError($detail['predictorGame'], $detail['actual']),
                $detail['status'],
                $detail['model'],
                $detail['fallback'],
            ];
        }

        (new Table($output))
            ->setHeaderTitle('Per-player predictions')
            ->setHeaders(['Game', 'Vest', 'Player', 'Target', 'Actual', 'Legacy', 'Predictor / 15 min', 'Predictor game', 'Status', 'Model', 'Fallback'])
            ->setRows($rows)
            ->render();
    }

    private function renderContext(OutputInterface $output, PredictionContext $context): void {
        $rows = [];
        foreach ($context->toFeatureMap() as $name => $value) {
            $rows[] = [$name, $value === null ? '<comment>null</comment>' : (string) $value];
        }

        (new Table($output))
            ->setHeaderTitle('Prediction context')
            ->setHeaders(['Feature', 'Value'])
            ->setRows($rows)
            ->render();
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     * @param non-empty-list<PredictionTarget> $targets
     */
    private function renderPredictions(
        OutputInterface $output,
        Player $player,
        PredictionContext $context,
        array $targets,
    ): void {
        $rows = [];
        foreach ($targets as $target) {
            $rows[] = $this->predictionRow($target, $player, $context, $output->isVerbose());
        }

        $headers = [
            'Target',
            'Actual',
            'Legacy',
            'Predictor / 15 min',
            'Predictor game',
            'Status',
        ];

        if ($output->isVerbose()) {
            $headers[] = 'Model';
            $headers[] = 'Fallback';
        }

        (new Table($output))
            ->setHeaderTitle('Predictions')
            ->setHeaders($headers)
            ->setRows($rows)
            ->render();
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     * @return non-empty-list<string>
     */
    private function predictionRow(
        PredictionTarget $target,
        Player $player,
        PredictionContext $context,
        bool $verbose,
    ): array {
        $detail = $this->predictionDetail($target, $player, $context);
        $row = [
            $detail['target'],
            $this->formatNullable($detail['actual']),
            $this->formatValueWithError($detail['legacy'], $detail['actual']),
            $detail['predictorPer15'] === null ? '-' : sprintf('%.3f', $detail['predictorPer15']),
            $this->formatValueWithError($detail['predictorGame'], $detail['actual']),
            $detail['status'] === 'ok' ? '<info>ok</info>' : '<error>' . $detail['status'] . '</error>',
        ];

        if ($verbose) {
            $row[] = $detail['model'];
            $row[] = $detail['fallback'];
        }

        return $row;
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     * @return PredictionDetail
     */
    private function predictionDetail(PredictionTarget $target, Player $player, PredictionContext $context): array {
        $actual = $this->actualValue($target, $player);
        $legacy = $this->legacyExpectedValue($target, $player);

        try {
            $prediction = $this->predictor->predict($target, $context);

            return [
                'game'           => (string) $player->game->code,
                'vest'           => (string) $player->vest,
                'name'           => $player->name,
                'target'         => $target->value,
                'actual'         => $actual === null ? null : (float) $actual,
                'legacy'         => $legacy,
                'predictorPer15' => $prediction->mean,
                'predictorGame'  => $this->scalePredictionToGameLength($prediction, $context),
                'model'          => $prediction->modelId,
                'fallback'       => (string) $prediction->fallbackLevel,
                'status'         => 'ok',
            ];
        } catch (Throwable $e) {
            return [
                'game'           => (string) $player->game->code,
                'vest'           => (string) $player->vest,
                'name'           => $player->name,
                'target'         => $target->value,
                'actual'         => $actual === null ? null : (float) $actual,
                'legacy'         => $legacy,
                'predictorPer15' => null,
                'predictorGame'  => null,
                'model'          => '-',
                'fallback'       => '-',
                'status'         => $e->getMessage(),
            ];
        }
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     */
    private function actualValue(PredictionTarget $target, Player $player): ?int {
        return match ($target) {
            PredictionTarget::ENEMY_HITS => $player instanceof LasermaxxPlayer ? $player->hitsOther : $player->hits,
            PredictionTarget::ENEMY_DEATHS => $player instanceof LasermaxxPlayer ? $player->deathsOther : $player->deaths,
            PredictionTarget::TEAMMATE_HITS => $player instanceof LasermaxxPlayer ? $player->hitsOwn : null,
            PredictionTarget::TEAMMATE_DEATHS => $player instanceof LasermaxxPlayer ? $player->deathsOwn : null,
            default => null,
        };
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     */
    private function legacyExpectedValue(PredictionTarget $target, Player $player): ?float {
        try {
            return match ($target) {
                PredictionTarget::ENEMY_HITS => $player->getExpectedAverageHitCount(),
                PredictionTarget::ENEMY_DEATHS => $player->getExpectedAverageDeathCount(),
                PredictionTarget::TEAMMATE_HITS => $player instanceof LasermaxxPlayer
                    ? $player->getExpectedAverageTeammateHitCount()
                    : null,
                PredictionTarget::TEAMMATE_DEATHS => $player instanceof LasermaxxPlayer
                    ? $player->getExpectedAverageTeammateDeathCount()
                    : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function scalePredictionToGameLength(PredictionResult $prediction, PredictionContext $context): float {
        if (($prediction->metadata['unit'] ?? null) !== 'count_per_15_minutes') {
            return $prediction->mean;
        }

        return $prediction->mean * $context->gameLengthMinutes / 15;
    }

    private function formatNullable(?float $value): string {
        return $value === null ? '-' : sprintf('%.3f', $value);
    }

    private function formatError(?float $actual, ?float $expected): string {
        if ($actual === null || $expected === null) {
            return '-';
        }

        return sprintf('%+.3f', $actual - $expected);
    }

    private function formatValueWithError(?float $expected, ?float $actual): string {
        if ($expected === null) {
            return '-';
        }

        return sprintf('%s (%s)', $this->formatNullable($expected), $this->formatError($actual, $expected));
    }
}
