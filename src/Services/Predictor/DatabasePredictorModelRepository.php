<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Lg\Predictor\Contract\PredictorModelRepositoryInterface;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Dto\PredictorModel;
use Lsr\Lg\Predictor\Enum\PredictionTarget;
use Lsr\Lg\Predictor\Model\ModelArtifactImporter;

final readonly class DatabasePredictorModelRepository implements PredictorModelRepositoryInterface
{
    public const string TABLE = 'predictor_models';

    public function __construct(
        private ModelArtifactImporter $importer,
    ) {
    }

    /**
     * @throws Exception
     */
    public function findModel(PredictionTarget $target, PredictionContext $context): ?PredictorModel {
        $rows = DB::select(self::TABLE, ['payload'])
            ->where('[active] = 1')
            ->where('[target] = %s', $target->value)
            ->where('([system] IS NULL OR [system] = %s)', $context->system)
            ->where('([arena_id] IS NULL OR [arena_id] = %i)', $context->arenaId)
            ->where('([game_mode_id] IS NULL OR [game_mode_id] = %i)', $context->gameModeId)
            ->where('([mode_group] IS NULL OR [mode_group] = %s)', $context->modeGroup)
            ->where('([game_type] IS NULL OR [game_type] = %s)', $context->gameType->value)
            ->where('([team_count] IS NULL OR [team_count] = %i)', $context->teamCount)
            ->orderBy('[fallback_level]')
            ->asc()
            ->orderBy('[imported_at]')
            ->desc()
            ->orderBy('[id]')
            ->desc()
            ->fetchAll(cache: false);

        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if ( ! is_string($payload) || $payload === '') {
                continue;
            }

            return $this->importer->importJson($payload);
        }

        return null;
    }
}
