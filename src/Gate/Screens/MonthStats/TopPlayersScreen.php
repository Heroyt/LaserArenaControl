<?php

namespace App\Gate\Screens\MonthStats;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Player;
use App\Gate\Screens\GateScreen;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use Exception;
use Lsr\Caching\Cache;
use Lsr\Core\Constants;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Db\DB;
use Lsr\Interfaces\RequestInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * General screens that shows today stats and best players.
 */
class TopPlayersScreen extends GateScreen
{
    /** @var array<string, int[]>|null */
    private ?array $gameIdsTeam = null;
    /** @var array<string, int[]>|null */
    private ?array $gameIdsSolo = null;
    /** @var array<string, int[]>|null */
    private ?array $gameIds = null;

    private ?int $gameCount = null;

    private ?DateTimeInterface $today = null;

    public function __construct(
      Latte                  $latte,
      private readonly Cache $cache,
    ) {
        parent::__construct($latte);
    }

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Nejlepší hráči měsíce', domain: 'gate', context: 'screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující nejlepší hráče pro aktuální měsíc.',
          domain : 'gate',
          context: 'screens.description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.month.top_players';
    }

    public static function getGroup() : string {
        return lang('Měsíční statistiky', domain: 'gate', context: 'screens.groups');
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function run() : ResponseInterface {
        /** @var RequestInterface $request */
        $request = App::getInstance()->getRequest();
        if ($request instanceof Request) {
            /** @var string $date */
            $date = $request->getGet('date', 'now');
        }
        else {
            $date = (string) ($request->getQueryParams()['date'] ?? 'now');
        }
        $this->today = new DateTimeImmutable($date);
        $this->gameIds = null;
        $this->gameIdsTeam = null;
        $this->gameIdsSolo = null;
        $this->gameCount = null;

        /**
         * @var array<string, Player> $data
         * @var int $gameCount
         * @var string $hash
         */
        // @phpstan-ignore-next-line
        [$data, $gameCount, $hash] = $this->cache->load(
          'gate.month.'.$this->today->format('Ym').'.topPlayers',
          function () {
              // Get today's best players
              /**
               * @var Player|null $topScore
               * @phpstan-ignore missingType.generics
               */
              $topScore = null;
              /**
               * @var Player|null $topSkill
               * @phpstan-ignore missingType.generics
               */
              $topSkill = null;
              /**
               * @var Player|null $topHits
               * @phpstan-ignore missingType.generics
               */
              $topHits = null;
              /**
               * @var Player|null $topDeaths
               * @phpstan-ignore missingType.generics
               */
              $topDeaths = null;
              /**
               * @var Player|null $topAccuracy
               * @phpstan-ignore missingType.generics
               */
              $topAccuracy = null;
              /**
               * @var Player|null $topShots
               * @phpstan-ignore missingType.generics
               */
              $topShots = null;
              /**
               * @var Player|null $topHitsOwn
               * @phpstan-ignore missingType.generics
               */
              $topHitsOwn = null;

              if ($this->getGameCount() > 0) {
                  $topScore = $this->getTopPlayer('score');
                  $topSkill = $this->getTopPlayer('skill');
                  $topHits = $this->getTopPlayer('hits');
                  $topDeaths = $this->getTopPlayer('deaths');
                  $topAccuracy = $this->getTopPlayer('accuracy', conditions: [['[shots] >= 50']]);
                  $topShots = $this->getTopPlayer('shots');
                  $topHitsOwn = $this->getTopPlayer('hits_own', type: GameModeType::TEAM);
              }

              // Calculate current screen hash (for caching)
              $data = [
                'gameCount' => $this->getGameCount(),
                'score'     => [$topScore?->name, $topScore?->user?->getCode(), $topScore?->score],
                'skill'     => [$topSkill?->name, $topSkill?->user?->getCode(), $topSkill?->skill],
                'hits'      => [$topHits?->name, $topHits?->user?->getCode(), $topHits?->hits],
                'deaths'    => [$topDeaths?->name, $topDeaths?->user?->getCode(), $topDeaths?->deaths],
                'accuracy'  => [$topAccuracy?->name, $topAccuracy?->user?->getCode(), $topAccuracy?->accuracy],
                'shots'     => [$topShots?->name, $topShots?->user?->getCode(), $topShots?->shots],
                /** @phpstan-ignore-next-line */
                'hitsOwn'   => [$topHitsOwn?->name, $topHitsOwn?->user?->getCode(), $topHitsOwn?->hitsOwn],
              ];

              return [
                [
                  'topScore'    => $topScore,
                  'topSkill'    => $topSkill,
                  'topHits'     => $topHits,
                  'topDeaths'   => $topDeaths,
                  'topAccuracy' => $topAccuracy,
                  'topShots'    => $topShots,
                  'topHitsOwn'  => $topHitsOwn,
                ],
                $this->getGameCount(),
                md5($this->today->format('Ym').json_encode($data, JSON_THROW_ON_ERROR)),
              ];
          },
          [
            'tags'   => [
              'gate',
              'gate.widgets',
              'gate.widgets.topPlayers',
              'games/'.$this->today->format('Y-m'),
            ],
            'expire' => '1 days',
          ]
        );

        return $this->view(
          'gate/screens/topMonthPlayers',
          array_merge(
            [
              'monthName'  => lang(Constants::MONTH_NAMES[(int) $this->today->format('m')], context: 'month'),
              'year'       => $this->today->format('Y'),
              'screenHash' => $hash,
              'gameCount'  => $gameCount,
              'addJs'      => ['gate/topPlayers.js'],
              'addCss'     => ['gate/topPlayers.css'],
            ],
            $data
          )
        );
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getGameCount() : int {
        if (!isset($this->gameCount)) {
            $this->gameCount = 0;
            foreach ($this->getGameIds() as $gameIds) {
                $this->gameCount += count($gameIds);
            }
        }
        return $this->gameCount;
    }

    /**
     * @return array<string,int[]>
     * @throws Exception
     */
    private function getGameIds() : array {
        if (isset($this->gameIds)) {
            return $this->gameIds;
        }
        $monthStart = new DateTimeImmutable($this->today?->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($this->today?->format('Y-m-t'));

        $query = GameFactory::queryGames(true, fields: ['id_mode'])->where(
          'DATE(start) BETWEEN %d AND %d',
          $monthStart,
          $monthEnd
        )->where(
          'id_mode IN %sql',
          DB::select('game_modes', 'id_mode')->where('rankable = 1')->fluent
        );
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);

        $this->gameIds = [];
        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $this->gameIds[$system] = array_keys($g);
        }
        return $this->gameIds;
    }

    /**
     * @param  string  $field
     * @param  bool  $desc
     * @param  GameModeType|null  $type
     * @param  array{0:string,1?:mixed,2?:mixed,3?:mixed}[]  $conditions
     * @return Player|null
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    private function getTopPlayer(
      string        $field,
      bool          $desc = true,
      ?GameModeType $type = null,
      array         $conditions = []
    ) : Player | null {
        $gameIds = match ($type) {
            GameModeType::TEAM => $this->getGameIdsTeams(),
            GameModeType::SOLO => $this->getGameIdsSolo(),
            default            => $this->getGameIds(),
        };
        $q = PlayerFactory::queryPlayers($gameIds, ['hits_own'])->orderBy('['.$field.']');
        if ($desc) {
            $q->desc();
        }

        if (!empty($conditions)) {
            $q->where('%and', $conditions);
        }

        /** @var null|object{id_player:int,system:string} $player */
        $player = $q->fetch(cache: false);

        if (isset($player)) {
            return PlayerFactory::getById(
              (int) $player->id_player,
              ['system' => (string) $player->system]
            );
        }
        return null;
    }

    /**
     * @return array<string,int[]>
     * @throws Exception
     */
    private function getGameIdsTeams() : array {
        if (isset($this->gameIdsTeam)) {
            return $this->gameIdsTeam;
        }
        $monthStart = new DateTimeImmutable($this->today?->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($this->today?->format('Y-m-t'));

        $query = GameFactory::queryGames(true, fields: ['id_mode', 'game_type'])->where(
          'DATE(start) BETWEEN %d AND %d',
          $monthStart,
          $monthEnd
        )->where('game_type = %s', GameModeType::TEAM->value)->where(
          'id_mode IN %sql',
          DB::select('game_modes', 'id_mode')->where('rankable = 1')->fluent
        );
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);

        $this->gameIdsTeam = [];
        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $this->gameIdsTeam[$system] = array_keys($g);
        }
        return $this->gameIdsTeam;
    }

    /**
     * @return array<string,int[]>
     * @throws Exception
     */
    private function getGameIdsSolo() : array {
        if (isset($this->gameIdsSolo)) {
            return $this->gameIdsSolo;
        }
        $monthStart = new DateTimeImmutable($this->today?->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($this->today?->format('Y-m-t'));

        $query = GameFactory::queryGames(true, fields: ['id_mode', 'game_type'])->where(
          'DATE(start) BETWEEN %d AND %d',
          $monthStart,
          $monthEnd
        )->where('game_type = %s', GameModeType::SOLO->value)->where(
          'id_mode IN %sql',
          DB::select('game_modes', 'id_mode')->where('rankable = 1')->fluent
        );
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);

        $this->gameIdsSolo = [];
        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $this->gameIdsSolo[$system] = array_keys($g);
        }
        return $this->gameIdsSolo;
    }
}
