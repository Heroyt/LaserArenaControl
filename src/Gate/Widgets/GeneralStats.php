<?php

namespace App\Gate\Widgets;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use DateTimeInterface;

class GeneralStats implements WidgetInterface, WithGameIdsInterface
{
    use WithGameIds;

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

        $gameIds = $this->getGameIds($date, $date, $systems);

        // Get today's best players
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

        if (!empty($gameIds)) {
            $q = PlayerFactory::queryPlayers($gameIds);
            $topScores = $q->orderBy('[score]')->desc()->fetchAssoc('name', cache: false);
            if (!empty($topScores)) {
                $count = 0;
                foreach ($topScores as $score) {
                    $topScores[] = PlayerFactory::getById(
                      (int) $score->id_player,
                      ['system' => (string) $score->system]
                    );
                    if ((++$count) > 3) {
                        break;
                    }
                }
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|object{id_player:int,system:string} $topHits */
            $topHits = $q->orderBy('[hits]')->desc()->fetch(cache: false);
            if (isset($topHits)) {
                $topHits = PlayerFactory::getById(
                  (int) $topHits->id_player,
                  ['system' => (string) $topHits->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|object{id_player:int,system:string} $topDeaths */
            $topDeaths = $q->orderBy('[deaths]')->desc()->fetch(cache: false);
            if (isset($topDeaths)) {
                $topDeaths = PlayerFactory::getById(
                  (int) $topDeaths->id_player,
                  ['system' => (string) $topDeaths->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|object{id_player:int,system:string} $topAccuracy */
            $topAccuracy = $q->orderBy('[accuracy]')->desc()->fetch(cache: false);
            if (isset($topAccuracy)) {
                $topAccuracy = PlayerFactory::getById(
                  (int) $topAccuracy->id_player,
                  ['system' => (string) $topAccuracy->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|object{id_player:int,system:string} $topShots */
            $topShots = $q->orderBy('[shots]')->desc()->fetch(cache: false);
            if (isset($topShots)) {
                $topShots = PlayerFactory::getById(
                  (int) $topShots->id_player,
                  ['system' => (string) $topShots->system]
                );
            }
        }

        $gameCount = array_reduce($gameIds, static fn($value, $games) => $value + count($games), 0);
        $teamCount = empty($gameIds) ? 0 : TeamFactory::queryTeams($gameIds)->count();
        $playerCount = empty($gameIds) ? 0 : PlayerFactory::queryPlayers($gameIds)->count();

        $this->data = [
          'gameCount'   => $gameCount,
          'teamCount'   => $teamCount,
          'playerCount' => $playerCount,
          'topScores'   => $topScores,
          'topHits'     => $topHits,
          'topDeaths'   => $topDeaths,
          'topAccuracy' => $topAccuracy,
          'topShots'    => $topShots,
        ];
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
        $this->gameIds = null;
        return $this;
    }
}
