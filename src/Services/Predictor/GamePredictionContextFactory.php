<?php

declare(strict_types=1);

namespace App\Services\Predictor;

use App\Core\Info;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use Lsr\Lg\Predictor\Dto\PredictionContext;
use Lsr\Lg\Predictor\Enum\GameType;

final readonly class GamePredictionContextFactory
{
    private const float DEFAULT_GAME_LENGTH_MINUTES = 15.0;

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     */
    public function createForPlayer(Player $player): PredictionContext {
        $game = $player->game;
        $mode = $game->mode;
        $isSolo = $mode?->isSolo() ?? false;
        $playerCount = max(1, $game->playerCount);
        $teamPlayerCount = $isSolo ? 1 : max(1, $player->team->playerCount ?? 1);

        return new PredictionContext(
            system: $game::SYSTEM,
            arenaId: $this->getArenaId(),
            gameModeId: $mode?->id,
            modeGroup: $mode?->rankable === true ? 'rankable' : null,
            gameType: $isSolo ? GameType::SOLO : GameType::TEAM,
            teamCount: $isSolo ? $playerCount : max(1, $game->teams->count()),
            enemyCount: $isSolo ? max(0, $playerCount - 1) : max(0, $playerCount - $teamPlayerCount),
            teamPlayerCount: $teamPlayerCount,
            teammateCountExcludingSelf: $isSolo ? 0 : max(0, $teamPlayerCount - 1),
            gameLengthMinutes: $this->getGameLengthMinutes($player),
            rankable: $mode?->rankable,
        );
    }

    private function getArenaId(): ?int {
        $arenaId = Info::get('liga_arena_id');

        return is_numeric($arenaId) ? (int) $arenaId : null;
    }

    /**
     * @template G of Game
     * @template T of Team
     * @param Player<G, T> $player
     */
    private function getGameLengthMinutes(Player $player): float {
        $gameLength = $player->game->getRealGameLength();

        return $gameLength > 0.0 ? $gameLength : self::DEFAULT_GAME_LENGTH_MINUTES;
    }
}
