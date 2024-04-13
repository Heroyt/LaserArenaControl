<?php

namespace App\Gate\Screens\MonthStats;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Player;
use App\Gate\Screens\GateScreen;
use DateTimeImmutable;
use Dibi\Row;
use Lsr\Core\Constants;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 */
class TopPlayersScreen extends GateScreen
{

    /** @var array<string, int[]> */
    private array $gameIds = [];

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Nejlepší hráči měsíce', context: 'gate-screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující nejlepší hráče pro aktuální měsíc.',
          context: 'gate-screens-description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.month.top_players';
    }

    public static function getGroup() : string {
        return lang('Měsíční statistiky', context: 'gate-screens-groups');
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $date = (string) App::getRequest()->getGet('date', 'now');
        $today = new DateTimeImmutable($date);
        $monthStart = new DateTimeImmutable($today->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($today->format('Y-m-t'));

        $query = GameFactory::queryGames(true)
                            ->where(
                              'DATE(start) BETWEEN %d AND %d',
                              $monthStart,
                              $monthEnd
                            );
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);
        $gameCount = 0;

        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $this->gameIds[$system] = array_keys($g);
            $gameCount += count($g);
        }

        // Get today's best players
        /** @var Player|null $topScore */
        $topScore = null;
        /** @var Player|null $topSkill */
        $topSkill = null;
        /** @var Player|null $topHits */
        $topHits = null;
        /** @var Player|null $topDeaths */
        $topDeaths = null;
        /** @var Player|null $topAccuracy */
        $topAccuracy = null;
        /** @var Player|null $topShots */
        $topShots = null;
        /** @var Player|null $topHitsOwn */
        $topHitsOwn = null;

        if ($gameCount > 0) {
            $topScore = $this->getTopPlayer('score');
            $topSkill = $this->getTopPlayer('skill');
            $topHits = $this->getTopPlayer('hits');
            $topDeaths = $this->getTopPlayer('deaths');
            $topAccuracy = $this->getTopPlayer('accuracy');
            $topShots = $this->getTopPlayer('shots');
            $topHitsOwn = $this->getTopPlayer('hits_own');
        }

        // Calculate current screen hash (for caching)
        $data = [
          'gameCount' => $gameCount,
          'score'     => [$topScore?->name, $topScore?->user?->getCode(), $topScore?->score],
          'skill'     => [$topSkill?->name, $topSkill?->user?->getCode(), $topSkill?->skill],
          'hits'      => [$topHits?->name, $topHits?->user?->getCode(), $topHits?->hits],
          'deaths'    => [$topDeaths?->name, $topDeaths?->user?->getCode(), $topDeaths?->deaths],
          'accuracy'  => [$topAccuracy?->name, $topAccuracy?->user?->getCode(), $topAccuracy?->accuracy],
          'shots'     => [$topShots?->name, $topShots?->user?->getCode(), $topShots?->shots],
          'hitsOwn'   => [$topHitsOwn?->name, $topHitsOwn?->user?->getCode(), $topHitsOwn?->hitsOwn],
        ];

        return $this->view(
          'gate/screens/topMonthPlayers',
          [
            'monthName'   => lang(Constants::MONTH_NAMES[(int) $today->format('m')], context: 'month'),
            'year'        => $today->format('Y'),
            'screenHash'  => md5(json_encode($data, JSON_THROW_ON_ERROR)),
            'gameCount'   => $gameCount,
            'topScore'    => $topScore,
            'topSkill'    => $topSkill,
            'topHits'     => $topHits,
            'topDeaths'   => $topDeaths,
            'topAccuracy' => $topAccuracy,
            'topShots'    => $topShots,
            'topHitsOwn'  => $topHitsOwn,
            'addJs'       => ['gate/topPlayers.js'],
            'addCss'      => ['gate/topPlayers.css'],
          ]
        );
    }

    private function getTopPlayer(string $field, bool $desc = true) : Player | null {
        $q = PlayerFactory::queryPlayers($this->gameIds, ['hits_own'])->orderBy('['.$field.']');
        if ($desc) {
            $q->desc();
        }

        /** @var null|Row{id_player:int,system:string} $player */
        $player = $q->fetch(cache: false);

        if (isset($player)) {
            return PlayerFactory::getById(
              (int) $player->id_player,
              ['system' => (string) $player->system]
            );
        }
        return null;
    }
}