<?php

namespace App\Gate\Screens\DayStats;

use App\Core\App;
use App\Gate\Screens\GateScreen;
use App\Gate\Widgets\GeneralStats;
use DateTimeImmutable;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 */
class GeneralStatsScreen extends GateScreen
{

    public function __construct(
      Latte                         $latte,
      private readonly GeneralStats $generalStats,
      private readonly Cache        $cache,
    ) {
        parent::__construct($latte);
    }

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

    public static function getGroup() : string {
        return lang('Denní statistiky', context: 'gate-screens-groups');
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

        $this->generalStats->refresh();

        [$generalStatsHash, $generalStatsData] = $this->cache->load(
          'gate.today.generalStats.'.$today->format('Y-m-d'),
          fn() => [
            $this->generalStats->getHash(date: $today, systems: $this->systems),
            [
              'data' => $this->generalStats->getData(date: $today, systems: $this->systems),
              'template' => $this->generalStats->getTemplate(),
            ],
          ],
          [
            $this->cache::Tags   => [
              'gate',
              'gate.widgets',
              'gate.widgets.generalStats',
              'games/'.$today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
          ]
        );


        return $this->view(
          'gate/screens/generalDayStats',
          [
            'screenHash' => $generalStatsHash,
            'widgets'    => [
              'generalStats' => $generalStatsData,
            ],
            'addJs'      => ['gate/today.js'],
            'addCss'     => ['gate/todayStats.css'],
          ]
        );
    }
}