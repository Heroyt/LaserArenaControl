<?php

namespace App\Tools\GameLoading;

/**
 * Game load data DTO
 *
 * @phpstan-type MetaLoadData array<string,string|null|numeric>|array{
 *     music: numeric|null,
 *     mode:string,
 *     loadTime:int,
 *     group?: numeric|string,
 *     type?: numeric|string,
 *     variations?: array<int, string>,
 * }
 */
class LasermaxxLoadData
{
    /**
     * @param  MetaLoadData  $meta
     * @param  LasermaxxLoadPlayerData[]  $players
     * @param  LasermaxxLoadTeamData[]  $teams
     */
    public function __construct(
      public array $meta = [],
      public array $players = [],
      public array $teams = [],
    ) {}

    /**
     * @return array{
     *     meta: MetaLoadData,
     *     players: LasermaxxLoadPlayerData[],
     *     teams: LasermaxxLoadTeamData[],
     *     metaString: string
     * }
     */
    public function getParams() : array {
        return [
          'meta'    => $this->meta,
          'players' => $this->players,
          'teams'   => $this->teams,
          'metaString' => $this->encodeMeta(),
        ];
    }

    /**
     * @return string
     */
    public function encodeMeta() : string {
        $meta = json_encode($this->meta, JSON_THROW_ON_ERROR);
        $meta = gzdeflate($meta, 9);
        if (!is_string($meta)) {
            return '';
        }
        $meta = gzdeflate($meta, 9);
        if (!is_string($meta)) {
            return '';
        }
        return base64_encode($meta);
    }

    /**
     * Filters teams that have at least 1 player
     *
     * @post All teams with less than 1 player are removed
     * @return void
     */
    public function filterTeams() : void {
        $this->teams = array_filter($this->teams, static fn($team) => $team->playerCount > 0);
    }

    /**
     * Sorts players by vest
     *
     * @return void
     */
    public function sortPlayers() : void {
        ksort($this->players);
    }
}
