<?php

namespace App\Gate\Screens\DayStats\WithRtsp;

use App\Core\App;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\RtspSettings;
use App\Gate\Widgets\GeneralStats;
use DateTimeImmutable;
use Lsr\Caching\Cache;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 *
 * @implements WithSettings<RtspSettings>
 */
class GeneralStatsWithRtspScreen extends GateScreen implements WithSettings
{
    private RtspSettings $settings;

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
        return lang('Základní denní statistiky s kamerami', domain: 'gate', context: 'screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující dnešní nejlepší hráče a počet odehraných her.',
          domain : 'gate',
          context: 'screens.description'
        );
    }

    public static function getGroup() : string {
        return lang('Denní statistiky', domain: 'gate', context: 'screens.groups');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.stats_cameras';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/rtsp.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        return new RtspSettings(
          array_filter(array_map('trim', explode("\n", $data['streams'] ?? ''))),
          (int) ($data['max-streams'] ?? 9),
        );
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $date = (string) App::getInstance()->getRequest()->getGet('date', 'now');
        $today = new DateTimeImmutable($date);

        $this->generalStats->refresh();

        [$generalStatsHash, $generalStatsData] = $this->cache->load(
          'gate.today.generalStats.'.$today->format('Y-m-d'),
          fn() => [
            $this->generalStats->getHash(date: $today, systems: $this->systems),
            [
              'data'     => $this->generalStats->getData(date: $today, systems: $this->systems),
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
          'gate/screens/generalDayStatsRtsp',
          [
            'settings' => $this->getSettings(),
            'screenHash' => $generalStatsHash,
            'widgets'    => [
              'generalStats' => $generalStatsData,
            ],
            'addJs'    => ['gate/todayRtsp.js'],
            'addCss'   => ['gate/todayStatsRtsp.css'],
          ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings() : RtspSettings {
        if (!isset($this->settings)) {
            $this->settings = new RtspSettings();
        }
        return $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function setSettings(GateSettings $settings) : static {
        $this->settings = $settings;
        return $this;
    }
}
