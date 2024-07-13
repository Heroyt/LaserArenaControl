<?php

namespace App\Models\DataObjects\NewGame;

class GameLoadData
{
    /** @var PlayerLoadData[]  */
    public array $players = [];
    /** @var TeamLoadData[] */
    public array $teams = [];
    public int $playerCount = 0;
    public ?int $playlist = null;
    public ?ModeLoadData $mode = null;
    public ?MusicLoadData $music = null;

    public function addPlayer(PlayerLoadData $player): void {
        $this->players[$player->vest] = $player;
    }
    public function removePlayer(PlayerLoadData $player): void {
        if (isset($this->players[$player->vest])) {
            unset($this->players[$player->vest]);
        }
    }
    public function addTeam(TeamLoadData $team): void {
        $this->teams[$team->color] = $team;
    }
    public function removeTeam(TeamLoadData $team): void {
        if (isset($this->teams[$team->color])) {
            unset($this->teams[$team->color]);
        }
    }
}
