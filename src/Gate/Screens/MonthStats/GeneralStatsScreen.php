<?php

namespace App\Gate\Screens\MonthStats;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Gate\Screens\GateScreen;
use App\Gate\Widgets\GeneralStatsData;
use DateTimeImmutable;
use Dibi\Row;
use Lsr\Core\Constants;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 */
class GeneralStatsScreen extends GateScreen
{
    use GeneralStatsData;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Základní měsíční statistiky', context: 'screens', domain: 'gate');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující nejlepší hráče a počet odehraných her pro aktuální měsíc.',
          context: 'screens.description',
          domain : 'gate'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.month.stats';
    }

    public static function getGroup() : string {
        return lang('Měsíční statistiky', context: 'screens.groups', domain: 'gate');
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        /** @var Request $request */
        $request = App::getInstance()->getRequest();
        /** @var string $date */
        $date = $request->getGet('date', 'now');
        $today = new DateTimeImmutable($date);
        $monthStart = new DateTimeImmutable($today->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($today->format('Y-m-t'));

        $query = GameFactory::queryGames(true, fields: ['id_mode'])
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
        /** @var array<string, int[]> $gameIdsAll */
        $gameIdsAll = [];
        /** @var array<string, int[]> $gameIdsRankable */
        $gameIdsRankable = [];

        /** @var int[] $rankableModeIds */
        $rankableModeIds = DB::select(AbstractMode::TABLE, 'id_mode')
                             ->where('[rankable] = true')
                             ->cacheTags(AbstractMode::TABLE)
                             ->fetchPairs();

        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $gameIdsAll[$system] = array_keys($g);
            $gameIdsRankable[$system] = array_keys(
              array_filter(
                $g,
                static fn(Row $game) => in_array((int) $game->id_mode, $rankableModeIds, true)
              )
            );
        }

        // Get top players data
        $params = $this->getTopPlayersData($gameIdsRankable, $gameIdsAll);

        // Calculate current screen hash (for caching)
        $hashData = [
          'gameCount'   => $params['gameCount'],
          'teamCount'   => $params['teamCount'],
          'playerCount' => $params['playerCount'],
          'scores'      => [],
          'hits'        => [
            $params['topHits']?->name,
            $params['topHits']?->user?->getCode(),
            $params['topHits']?->hits,
          ],
          'deaths'      => [
            $params['topDeaths']?->name,
            $params['topDeaths']?->user?->getCode(),
            $params['topDeaths']?->deaths,
          ],
          'accuracy'    => [
            $params['topAccuracy']?->name,
            $params['topAccuracy']?->user?->getCode(),
            $params['topAccuracy']?->accuracy,
          ],
          'shots'       => [
            $params['topShots']?->name,
            $params['topShots']?->user?->getCode(),
            $params['topShots']?->shots,
          ],
        ];
        foreach ($params['topScores'] as $player) {
            $hashData['scores'][] = [$player->name, $player->user?->getCode(), $player->score];
        }

        // Add additional params for template
        $params['screenHash'] = md5($today->format('Ym').json_encode($hashData, JSON_THROW_ON_ERROR));
        $params['monthName'] = lang(Constants::MONTH_NAMES[(int) $today->format('m')], context: 'month');
        $params['year'] = $today->format('Y');
        $params['addJs'] = ['gate/today.js'];
        $params['addCss'] = ['gate/todayStats.css'];

        return $this->view('gate/screens/generalMonthStats', $params);
    }
}
