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
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Exception\PredictionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
        return 'Compare predictor output with legacy expected values for one player result.';
    }

    protected function configure(): void {
        $this->addArgument('code', InputArgument::REQUIRED, 'Game code.');
        $this->addArgument('vest', InputArgument::REQUIRED, 'Player vest number.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Prediction target. Can be used more than once.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $game = GameFactory::getByCode((string) $input->getArgument('code'));
        if ($game === null) {
            $output->writeln('<error>Game was not found.</error>');

            return self::FAILURE;
        }

        $player = $this->findPlayer($game, (string) $input->getArgument('vest'));
        if ($player === null) {
            $output->writeln('<error>Player vest was not found in the game.</error>');

            return self::FAILURE;
        }

        $context = $this->contextFactory->createForPlayer($player);
        $targetOption = $input->getOption('target');
        $targets = $this->parseTargets(is_array($targetOption) ? array_values($targetOption) : []);

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
            $rows[] = $this->predictionRow($target, $player, $context);
        }

        (new Table($output))
            ->setHeaderTitle('Predictions')
            ->setHeaders(['Target', 'Legacy expected', 'Predictor mean', 'Model', 'Fallback', 'Status'])
            ->setRows($rows)
            ->render();
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     * @return array{string,string,string,string,string,string}
     */
    private function predictionRow(PredictionTarget $target, Player $player, PredictionContext $context): array {
        $legacy = $this->legacyExpectedValue($target, $player);

        try {
            $prediction = $this->predictor->predict($target, $context);

            return [
                $target->value,
                $legacy,
                sprintf('%.3f', $prediction->mean),
                $prediction->modelId,
                (string) $prediction->fallbackLevel,
                '<info>ok</info>',
            ];
        } catch (Throwable $e) {
            return [
                $target->value,
                $legacy,
                '-',
                '-',
                '-',
                '<error>' . $e->getMessage() . '</error>',
            ];
        }
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     */
    private function legacyExpectedValue(PredictionTarget $target, Player $player): string {
        try {
            return match ($target) {
                PredictionTarget::ENEMY_HITS => sprintf('%.3f', $player->getExpectedAverageHitCount()),
                PredictionTarget::ENEMY_DEATHS => sprintf('%.3f', $player->getExpectedAverageDeathCount()),
                PredictionTarget::TEAMMATE_HITS => $player instanceof LasermaxxPlayer
                    ? sprintf('%.3f', $player->getExpectedAverageTeammateHitCount())
                    : '-',
                PredictionTarget::TEAMMATE_DEATHS => $player instanceof LasermaxxPlayer
                    ? sprintf('%.3f', $player->getExpectedAverageTeammateDeathCount())
                    : '-',
                default => '-',
            };
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }
}
