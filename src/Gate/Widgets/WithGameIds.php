<?php

namespace App\Gate\Widgets;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Db\DB;

trait WithGameIds
{
    /**
     * @var array{rankable:array<string, int[]>|null,all:array<string, int[]>|null}
     */
    protected array $gameIds = [
      'rankable' => null,
      'all'      => null,
    ];

    /** @var int[] */
    protected array $rankableModeIds {
        get {
            if (!isset($this->rankableModeIds)) {
                /** @var int[] $rows */
                $rows = DB::select(AbstractMode::TABLE, 'id_mode')
                          ->where('[rankable] = true')
                          ->cacheTags(AbstractMode::TABLE)
                          ->fetchPairs();
                $this->rankableModeIds = $rows;
            }
            return $this->rankableModeIds;
        }
    }

    public function getGameIds(
      ?DateTimeInterface $dateFrom = null,
      ?DateTimeInterface $dateTo = null,
      ?array $systems = [],
      bool   $rankableOnly = false,
    ) : array {
        if (
          ($rankableOnly && $this->gameIds['rankable'] === null)
          || (!$rankableOnly && $this->gameIds['all'] === null)
        ) {
            $gameIds = [];
            $dateFrom ??= new DateTimeImmutable();
            $dateTo ??= new DateTimeImmutable();
            $query = GameFactory::queryGames(true, fields: ['id_mode'])
                                ->where('DATE([start]) BETWEEN %d AND %d', $dateFrom, $dateTo);
            if (isset($systems) && count($systems) > 0) {
                $query->where('[system] IN %in', $systems);
            }

            if ($rankableOnly) {
                $query->where('[id_mode] IN %in', $this->rankableModeIds);
            }

            /** @var array<string,array<int,object{id_game:int,system:string,id_mode:int,start:DateTimeInterface,end:DateTimeInterface}>> $games */
            $games = $query->fetchAssoc('system|id_game', cache: false);
            foreach ($games as $system => $systemGames) {
                $gameIds[$system] ??= array_keys($systemGames);
            }

            if ($rankableOnly) {
                $this->gameIds['rankable'] = $gameIds;
            }
            else {
                $this->gameIds['all'] = $gameIds;
            }
        }
        if ($rankableOnly) {
            assert($this->gameIds['rankable'] !== null);
            return $this->gameIds['rankable'];
        }
        assert($this->gameIds['all'] !== null);
        return $this->gameIds['all'];
    }

    public function setGameIds(?array $gameIds) : static {
        // Reset all game IDs if null is provided
        if ($gameIds === null) {
            $this->gameIds = [
              'rankable' => null,
              'all'      => null,
            ];
            return $this;
        }

        // Check if the provided array is not already in the expected format
        if (!isset($gameIds['rankable']) && !isset($gameIds['all'])) {
            /** @var array<string, int[]> $gameIds */
            $this->gameIds = [
              'rankable' => null,
              'all'      => $gameIds, // Treat as 'all' game IDs
            ];
            return $this;
        }

        /** @var array{rankable:array<string, int[]>|null, all:array<string, int[]>|null} $gameIds */
        $this->gameIds = $gameIds;
        return $this;
    }
}
