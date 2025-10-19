<?php

namespace App\Gate\Widgets;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use DateTimeInterface;

class GeneralStats implements WidgetInterface, WithGameIdsInterface
{
    use WithGameIds;
    use GeneralStatsData;

    /**
     * @var array{gameCount: int, teamCount: int, playerCount: int, topScores: Player[], topShots: Player|null,
     *   topHits: Player|null, topDeaths: Player|null, topAccuracy: Player|null}|null
     */
    private ?array $data = null;
    private ?string $hash = null;

    /**
     * @inheritDoc
     */
    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : string {
        if (isset($this->hash)) {
            return $this->hash;
        }
        $data = $this->getData($game, $date, $systems);
        $hash = $data['gameCount'].$data['teamCount'].$data['playerCount'];
        foreach ($data['topScores'] as $player) {
            $hash .= $player->name.$player->score;
        }
        $hash .= isset($data['topAccuracy']) ? $data['topAccuracy']->name.$data['topAccuracy']->accuracy : '';
        $hash .= isset($data['topShots']) ? $data['topShots']->name.$data['topShots']->accuracy : '';
        $hash .= isset($data['topHits']) ? $data['topHits']->name.$data['topHits']->accuracy : '';
        $hash .= isset($data['topDeaths']) ? $data['topDeaths']->name.$data['topDeaths']->accuracy : '';
        $this->hash = md5($hash);
        return $this->hash;
    }

    /**
     * @inheritDoc
     */
    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : array {
        if (isset($this->data)) {
            return $this->data;
        }

        $gameIdsAll = $this->getGameIds($date, $date, $systems);
        $gameIdsRankable = $this->getGameIds($date, $date, $systems, true);

        $this->data = $this->getTopPlayersData($gameIdsRankable, $gameIdsAll);
        return $this->data;
    }

    public function getTemplate() : string {
        return 'generalStats.latte';
    }

    public function getSettingsTemplate() : string {
        return '';
    }

    public function refresh() : static {
        $this->data = null;
        $this->hash = null;
        $this->gameIds['rankable'] = null;
        $this->gameIds['all'] = null;
        return $this;
    }
}
