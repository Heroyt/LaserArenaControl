<?php

declare(strict_types=1);

namespace App\Gate\Widgets;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Player;

trait GeneralStatsData
{
    /**
     * @template P of Player
     * @param  array<string, int[]>  $gameIdsRankable
     * @param  array<string, int[]>  $gameIdsAll
     * @return array{
     *     topScores: P[],
     *     topHits: P|null,
     *     topDeaths: P|null,
     *     topAccuracy: P|null,
     *     topShots: P|null,
     *     gameCount: int<0, max>,
     *     teamCount: int<0, max>,
     *     playerCount: int<0, max>
     *         }
     * @phpstan-ignore method.templateTypeNotInParameter
     */
    protected function getTopPlayersData(array $gameIdsRankable, array $gameIdsAll) : array {
        /**
         * @var P[] $topScores
         */
        $topScores = [];
        /**
         * @var P|null $topHits
         */
        $topHits = null;
        /**
         * @var P|null $topDeaths
         */
        $topDeaths = null;
        /**
         * @var P|null $topAccuracy
         */
        $topAccuracy = null;
        /**
         * @var P|null $topShots
         */
        $topShots = null;

        if (!empty($gameIdsRankable)) {
            /** @var P[] $topScores */
            $topScores = $this->getTopPlayers('score', 3, $gameIdsRankable);
            /** @var P $topHits */
            $topHits = $this->getTopPlayer('hits', $gameIdsRankable);
            /** @var P $topDeaths */
            $topDeaths = $this->getTopPlayer('deaths', $gameIdsRankable);
            /** @var P $topAccuracy */
            $topAccuracy = $this->getTopPlayer('accuracy', $gameIdsRankable, conditions: [['[shots] >= 50']]);
            /** @var P $topShots */
            $topShots = $this->getTopPlayer('shots', $gameIdsRankable);
        }

        $gameCount = $this->getGameCount($gameIdsAll);
        $teamCount = $this->getTeamCount($gameIdsAll);
        $playerCount = $this->getPlayerCount($gameIdsAll);

        return [
          'topScores'   => $topScores,
          'topHits'     => $topHits,
          'topDeaths'   => $topDeaths,
          'topAccuracy' => $topAccuracy,
          'topShots'    => $topShots,
          'gameCount'   => $gameCount,
          'teamCount'   => $teamCount,
          'playerCount' => $playerCount,
        ];
    }

    /**
     * @param  non-empty-string  $statType
     * @param  array<string,int[]>  $gameIds
     * @param  bool  $desc
     * @param  array{0:string,1?:mixed,2?:mixed,3?:mixed}[]  $conditions
     * @return Player[]
     * @phpstan-ignore missingType.generics
     */
    protected function getTopPlayers(
      string $statType,
      int    $count,
      array  $gameIds,
      bool   $desc = true,
      array  $conditions = []
    ) : array {
        $q = PlayerFactory::queryPlayers($gameIds)
                          ->orderBy('%n', $statType);
        if ($desc) {
            $q->desc();
        }
        if (!empty($conditions)) {
            $q->where('%and', $conditions);
        }
        $q->limit($count);
        /** @var object{id_player:int,system:string}[] $topPlayers */
        $topPlayers = $q->fetchAll(cache: false);
        $players = [];
        foreach ($topPlayers as $topPlayer) {
            $player = PlayerFactory::getById(
              (int) $topPlayer->id_player,
              ['system' => (string) $topPlayer->system]
            );
            if ($player !== null) {
                $players[] = $player;
            }
        }
        return $players;
    }

    /**
     * @param  non-empty-string  $statType
     * @param  array<string,int[]>  $gameIds
     * @param  bool  $desc
     * @param  array{0:string,1?:mixed,2?:mixed,3?:mixed}[]  $conditions
     * @return Player|null
     * @phpstan-ignore missingType.generics
     */
    protected function getTopPlayer(
      string $statType,
      array  $gameIds,
      bool   $desc = true,
      array  $conditions = []
    ) : ?Player {
        $q = PlayerFactory::queryPlayers($gameIds)
                          ->orderBy('%n', $statType);
        if ($desc) {
            $q->desc();
        }
        if (!empty($conditions)) {
            $q->where('%and', $conditions);
        }
        /** @var null|object{id_player:int,system:string} $topPlayer */
        $topPlayer = $q->fetch(cache: false);
        if (isset($topPlayer)) {
            return PlayerFactory::getById(
              (int) $topPlayer->id_player,
              ['system' => (string) $topPlayer->system]
            );
        }
        return null;
    }

    /**
     * @param  array<string, int[]>  $gameIdsAll
     * @return int<0, max>
     */
    protected function getGameCount(array $gameIdsAll) : int {
        $count = (int) array_reduce($gameIdsAll, static fn($value, $games) => $value + count($games), 0);
        assert($count >= 0);
        return $count;
    }

    /**
     * @param  array<string, int[]>  $gameIds
     * @return int<0, max>
     */
    protected function getTeamCount(array $gameIds) : int {
        if (empty($gameIds)) {
            return 0;
        }
        $count = TeamFactory::queryTeams($gameIds)->count();
        assert($count >= 0);
        return $count;
    }

    /**
     * @param  array<string, int[]>  $gameIds
     * @return int<0, max>
     */
    protected function getPlayerCount(array $gameIds) : int {
        if (empty($gameIds)) {
            return 0;
        }
        $count = PlayerFactory::queryPlayers($gameIds)->count();
        assert($count >= 0);
        return $count;
    }
}
