<?php

namespace App\Gate\Screens\DayStats\WithRtsp;

use App\Core\App;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\RtspSettings;
use App\Gate\Widgets\Highlights;
use App\Gate\Widgets\MusicCount;
use App\Gate\Widgets\TopPlayerSkills;
use DateTimeImmutable;
use Lsr\Caching\Cache;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 *
 * @implements WithSettings<RtspSettings>
 */
class HighlightsWithRtspScreen extends GateScreen implements WithSettings
{
    private RtspSettings $settings;

    public function __construct(
      Latte                            $latte,
      private readonly Highlights      $highlights,
      private readonly MusicCount      $musicCount,
      private readonly TopPlayerSkills $topPlayerSkills,
      private readonly Cache           $cache,
    ) {
        parent::__construct($latte);
    }

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Dnešní zajímavosti z her s kamerami', domain: 'gate', context: 'screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující zajímavosti z dnešních odehraných her.',
          domain : 'gate',
          context: 'screens.description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.highlights_cameras';
    }

    public static function getGroup() : string {
        return lang('Denní statistiky', domain: 'gate', context: 'screens.groups');
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
        /** @var Request $request */
        $request = App::getInstance()->getRequest();
        /** @var string $date */
        $date = $request->getGet('date', 'now');
        $today = new DateTimeImmutable($date);

        $this->highlights->refresh();
        $this->musicCount->refresh();
        $this->topPlayerSkills->refresh();

        [$highlightsHash, $highlightsData] = $this->cache->load(
          'gate.today.highlights.'.$today->format('Y-m-d'),
          fn() => [
            $this->highlights->getHash(date: $today),
            [
              'data'     => $this->highlights->getData(date: $today),
              'template' => $this->highlights->getTemplate(),
            ],
          ],
          [
            'tags'   => [
              'gate',
              'gate.widgets',
              'gate.widgets.highlights',
              'games/'.$today->format('Y-m-d'),
            ],
            'expire' => '1 days',
          ]
        );

        [$musicHash, $musicData, $musicGameIds, $musicGameIdsRankable] = $this->cache->load(
          'gate.today.musicCounts.'.$today->format('Y-m-d'),
          fn() => [
            $this->musicCount->getHash(date: $today, systems: $this->systems),
            [
              'data'     => $this->musicCount->getData(date: $today, systems: $this->systems),
              'template' => $this->musicCount->getTemplate(),
            ],
            $this->musicCount->getGameIds(dateFrom: $today, dateTo: $today, systems: $this->systems),
            $this->musicCount->getGameIds(
              dateFrom    : $today,
              dateTo      : $today,
              systems     : $this->systems,
              rankableOnly: true
            ),
          ],
          [
            'tags'   => [
              'gate',
              'gate.widgets',
              'gate.widgets.musicCounts',
              'games/'.$today->format('Y-m-d'),
            ],
            'expire' => '1 days',
          ]
        );

        [$topPlayersHash, $topPlayersData] = $this->cache->load(
          'gate.today.topPlayers.'.$today->format('Y-m-d'),
          function () use ($today, $musicGameIds, $musicGameIdsRankable) {
              $this->topPlayerSkills->setGameIds(
                [
                  'all'      => $musicGameIds,
                  'rankable' => $musicGameIdsRankable,
                ]
              );
              return [
                $this->topPlayerSkills->getHash(date: $today, systems: $this->systems),
                [
                  'data'     => $this->topPlayerSkills->getData(date: $today, systems: $this->systems),
                  'template' => $this->topPlayerSkills->getTemplate(),
                ],
              ];
          },
          [
            'tags'   => [
              'gate',
              'gate.widgets',
              'gate.widgets.topPlayers',
              'games/'.$today->format('Y-m-d'),
            ],
            'expire' => '1 days',
          ]
        );

        return $this->view(
          'gate/screens/todayHighlightsRtsp',
          [
            'settings'   => $this->getSettings(),
            'screenHash' => md5($highlightsHash.$musicHash.$topPlayersHash),
            'widgets'    => [
              'highlights' => $highlightsData,
              'music'      => $musicData,
              'skills'     => $topPlayersData,
            ],
            'addJs'      => ['gate/todayHighlightsRtsp.js'],
            'addCss'     => ['gate/todayHighlightsRtsp.css'],
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
