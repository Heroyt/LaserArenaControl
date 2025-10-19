<?php

declare(strict_types=1);

namespace App\Gate\Widgets;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Player;

trait GeneralStatsData
{
    /**
     * @param  array<string, int[]>  $gameIdsRankable
     * @param  array<string, int[]>  $gameIdsAll
     * @return array{
     *     topScores: Player[],
     *     topHits: Player|null,
     *     topDeaths: Player|null,
     *     topAccuracy: Player|null,
     *     topShots: Player|null,
     *     gameCount: int<0, max>,
     *     teamCount: int<0, max>,
     *     playerCount: int<0, max>
     *         }
     */
    protected function getTopPlayersData(array $gameIdsRankable, array $gameIdsAll) : array {
        /** @var Player[] $topScores */
        $topScores = [];
        /** @var Player|null $topHits */
        $topHits = null;
        /** @var Player|null $topDeaths */
        $topDeaths = null;
        /** @var Player|null $topAccuracy */
        $topAccuracy = null;
        /** @var Player|null $topShots */
        $topShots = null;

        if (!empty($gameIdsRankable)) {
            $topScores = $this->getTopPlayers('score', 3, $gameIdsRankable);
            $topHits = $this->getTopPlayer('hits', $gameIdsRankable);
            $topDeaths = $this->getTopPlayer('deaths', $gameIdsRankable);
            $topAccuracy = $this->getTopPlayer('accuracy', $gameIdsRankable, conditions: [['[shots] >= 50']]);
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
            $players[] = PlayerFactory::getById(
              (int) $topPlayer->id_player,
              ['system' => (string) $topPlayer->system]
            );
        }
        return $players;
    }

    /**
     * @param  non-empty-string  $statType
     * @param  array<string,int[]>  $gameIds
     * @param  bool  $desc
     * @param  array{0:string,1?:mixed,2?:mixed,3?:mixed}[]  $conditions
     * @return Player|null
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
