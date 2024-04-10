<?php

namespace App\Gate\Screens;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\GameModels\Game\Player;
use DateTimeImmutable;
use Dibi\Row;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 */
class GeneralDayStatsScreen extends GateScreen
{

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Základní denní statistiky', context: 'gate-screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující dnešní nejlepší hráče a počet odehraných her.',
          context: 'gate-screens-description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.stats';
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $date = (string) App::getRequest()->getGet('date', 'now');
        $today = new DateTimeImmutable($date);
        $query = GameFactory::queryGames(true, $today);
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);
        /** @var array<string, int[]> $gameIds */
        $gameIds = [];
        $gameCount = 0;

        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $gameIds[$system] = array_keys($g);
            $gameCount += count($g);
        }

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
            /** @var null|Row{id_player:int,system:string} $topHits */
            $topHits = $q->orderBy('[hits]')->desc()->fetch(cache: false);
            if (isset($topHits)) {
                $topHits = PlayerFactory::getById(
                  (int) $topHits->id_player,
                  ['system' => (string) $topHits->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|Row{id_player:int,system:string} $topDeaths */
            $topDeaths = $q->orderBy('[deaths]')->desc()->fetch(cache: false);
            if (isset($topDeaths)) {
                $topDeaths = PlayerFactory::getById(
                  (int) $topDeaths->id_player,
                  ['system' => (string) $topDeaths->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|Row{id_player:int,system:string} $topAccuracy */
            $topAccuracy = $q->orderBy('[accuracy]')->desc()->fetch(cache: false);
            if (isset($topAccuracy)) {
                $topAccuracy = PlayerFactory::getById(
                  (int) $topAccuracy->id_player,
                  ['system' => (string) $topAccuracy->system]
                );
            }
            $q = PlayerFactory::queryPlayers($gameIds);
            /** @var null|Row{id_player:int,system:string} $topShots */
            $topShots = $q->orderBy('[shots]')->desc()->fetch(cache: false);
            if (isset($topShots)) {
                $topShots = PlayerFactory::getById(
                  (int) $topShots->id_player,
                  ['system' => (string) $topShots->system]
                );
            }
        }

        $teamCount = empty($gameIds) ? 0 : TeamFactory::queryTeams($gameIds)->count();
        $playerCount = empty($gameIds) ? 0 : PlayerFactory::queryPlayers($gameIds)->count();

        // Calculate current screen hash (for caching)
        $data = [
          'gameCount'   => $gameCount,
          'teamCount'   => $teamCount,
          'playerCount' => $playerCount,
          'scores'      => [],
          'hits'        => [$topHits?->name, $topHits?->user?->getCode(), $topHits?->hits],
          'deaths'      => [$topDeaths?->name, $topDeaths?->user?->getCode(), $topDeaths?->deaths],
          'accuracy'    => [$topAccuracy?->name, $topAccuracy?->user?->getCode(), $topAccuracy?->accuracy],
          'shots'       => [$topShots?->name, $topShots?->user?->getCode(), $topShots?->shots],
        ];
        foreach ($topScores as $player) {
            $data['scores'][] = [$player->name, $player->user?->getCode(), $player->score];
        }


        return $this->view(
          'gate/screens/generalDayStats',
          [
            'screenHash'  => md5(json_encode($data, JSON_THROW_ON_ERROR)),
            'gameCount'   => $gameCount,
            'teamCount'   => $teamCount,
            'playerCount' => $playerCount,
            'topScores'   => $topScores,
            'topHits'     => $topHits,
            'topDeaths'   => $topDeaths,
            'topAccuracy' => $topAccuracy,
            'topShots'    => $topShots,
            'addJs'       => ['gate/today.js'],
            'addCss'      => ['gate/todayStats.css'],
          ]
        );
    }
}