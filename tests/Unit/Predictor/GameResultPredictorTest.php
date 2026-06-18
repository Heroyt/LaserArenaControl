<?php

declare(strict_types=1);

namespace Tests\Unit\Predictor;

use App\Services\Predictor\FilesystemPredictorModelRepository;
use App\Services\Predictor\GameResultPredictor;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Enum\GameType;
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Model\ModelArtifactImporter;
use Lsr\Lg\Predictor\Runtime\ContextRidgePolynomialEvaluator;
use PHPUnit\Framework\TestCase;

final class GameResultPredictorTest extends TestCase
{
    private string $modelDirectory;

    protected function setUp(): void {
        parent::setUp();

        $this->modelDirectory = sys_get_temp_dir() . '/lac-predictor-test-' . bin2hex(random_bytes(8));
        mkdir($this->modelDirectory);
    }

    protected function tearDown(): void {
        foreach (glob($this->modelDirectory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->modelDirectory);

        parent::tearDown();
    }

    public function test_predicts_from_best_matching_filesystem_model(): void {
        file_put_contents(
            $this->modelDirectory . '/fallback.json',
            json_encode($this->modelPayload('fallback', fallbackLevel: 5, arenaId: null), JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->modelDirectory . '/arena.json',
            json_encode($this->modelPayload('arena', fallbackLevel: 1, arenaId: 1), JSON_THROW_ON_ERROR),
        );

        $repository = new FilesystemPredictorModelRepository($this->modelDirectory, new ModelArtifactImporter());
        $predictor = new GameResultPredictor($repository, [new ContextRidgePolynomialEvaluator()]);

        $result = $predictor->predict(PredictionTarget::ENEMY_HITS, $this->context(arenaId: 1));

        self::assertSame('fixture.arena', $result->modelId);
        self::assertSame(1, $result->fallbackLevel);
        self::assertEqualsWithDelta(43.5, $result->mean, 1.0e-10);
    }

    public function test_falls_back_to_generic_scope(): void {
        file_put_contents(
            $this->modelDirectory . '/fallback.json',
            json_encode($this->modelPayload('fallback', fallbackLevel: 5, arenaId: null), JSON_THROW_ON_ERROR),
        );

        $repository = new FilesystemPredictorModelRepository($this->modelDirectory, new ModelArtifactImporter());
        $predictor = new GameResultPredictor($repository, [new ContextRidgePolynomialEvaluator()]);

        $result = $predictor->predict(PredictionTarget::ENEMY_HITS, $this->context(arenaId: 9));

        self::assertSame('fixture.fallback', $result->modelId);
        self::assertSame(5, $result->fallbackLevel);
    }

    private function context(int $arenaId): PredictionContext {
        return new PredictionContext(
            system: 'evo5',
            arenaId: $arenaId,
            gameModeId: 2,
            modeGroup: null,
            gameType: GameType::TEAM,
            teamCount: 2,
            enemyCount: 4,
            teamPlayerCount: 3,
            teammateCountExcludingSelf: 2,
            gameLengthMinutes: 15,
            rankable: true,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function modelPayload(string $id, int $fallbackLevel, ?int $arenaId): array {
        return [
            'schema_version' => 1,
            'model_id'      => 'fixture.' . $id,
            'model_family'  => 'context_ridge_polynomial_v1',
            'target'        => 'enemy_hits',
            'output'        => [
                'unit'                     => 'count_per_15_minutes',
                'canonical_length_minutes' => 15,
                'clip_min'                 => 0,
                'rounding'                 => 'none',
            ],
            'scope'         => [
                'system'         => null,
                'arena_id'       => $arenaId,
                'game_mode_id'   => null,
                'mode_group'     => null,
                'game_type'      => null,
                'team_count'     => null,
                'fallback_level' => $fallbackLevel,
            ],
            'features'      => [
                'numeric'     => [
                    'enemy_count',
                    'team_player_count',
                ],
                'categorical' => [
                    'system',
                    'game_type',
                ],
            ],
            'preprocessing' => [
                'numeric_scaler'     => [
                    'type'  => 'standard',
                    'mean'  => [
                        'enemy_count'       => 2,
                        'team_player_count' => 1,
                    ],
                    'scale' => [
                        'enemy_count'       => 2,
                        'team_player_count' => 1,
                    ],
                ],
                'numeric_expansion'  => [
                    'type'         => 'polynomial',
                    'degree'       => 2,
                    'include_bias' => false,
                    'terms'        => [
                        [
                            'name'    => 'enemy_count',
                            'factors' => ['enemy_count'],
                        ],
                        [
                            'name'    => 'team_player_count',
                            'factors' => ['team_player_count'],
                        ],
                        [
                            'name'    => 'enemy_count^2',
                            'factors' => ['enemy_count', 'enemy_count'],
                        ],
                        [
                            'name'    => 'enemy_count team_player_count',
                            'factors' => ['enemy_count', 'team_player_count'],
                        ],
                        [
                            'name'    => 'team_player_count^2',
                            'factors' => ['team_player_count', 'team_player_count'],
                        ],
                    ],
                ],
                'categorical_encoder' => [
                    'type'           => 'one_hot',
                    'handle_unknown' => 'ignore',
                    'categories'     => [
                        'system'    => ['evo5', 'evo6'],
                        'game_type' => ['SOLO', 'TEAM'],
                    ],
                ],
            ],
            'coefficients'  => [
                'intercept' => 10,
                'features'  => [
                    [
                        'name'        => 'num__enemy_count',
                        'coefficient' => 2,
                    ],
                    [
                        'name'        => 'num__team_player_count',
                        'coefficient' => 3,
                    ],
                    [
                        'name'        => 'num__enemy_count^2',
                        'coefficient' => 4,
                    ],
                    [
                        'name'        => 'num__enemy_count team_player_count',
                        'coefficient' => 5,
                    ],
                    [
                        'name'        => 'num__team_player_count^2',
                        'coefficient' => 1,
                    ],
                    [
                        'name'        => 'cat__system_evo5',
                        'coefficient' => 5,
                    ],
                    [
                        'name'        => 'cat__system_evo6',
                        'coefficient' => -1,
                    ],
                    [
                        'name'        => 'cat__game_type_SOLO',
                        'coefficient' => -3,
                    ],
                    [
                        'name'        => 'cat__game_type_TEAM',
                        'coefficient' => 2.5,
                    ],
                ],
            ],
            'metrics'       => [],
        ];
    }
}
