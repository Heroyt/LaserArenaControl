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
use App\Services\GameHighlight\GameHighlightService;
use DateTimeImmutable;
use Lsr\Core\Caching\Cache;
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
      Latte                                 $latte,
      private readonly GameHighlightService $highlightService,
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
        return lang('Dnešní zajímavosti z her s kamerami', context: 'gate-screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující zajímavosti z dnešních odehraných her.',
          context: 'gate-screens-description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.idle.highlights_cameras';
    }

    public static function getGroup() : string {
        return lang('Denní statistiky', context: 'gate-screens-groups');
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
        $date = (string) App::getRequest()->getGet('date', 'now');
        $today = new DateTimeImmutable($date);

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
            $this->cache::Tags   => [
              'gate',
              'gate.widgets',
              'gate.widgets.highlights',
              'games/'.$today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
          ]
        );

        [$musicHash, $musicData, $musicGameIds] = $this->cache->load(
          'gate.today.musicCounts.'.$today->format('Y-m-d'),
          fn() => [
            $this->musicCount->getHash(date: $today, systems: $this->systems),
            [
              'data'     => $this->musicCount->getData(date: $today, systems: $this->systems),
              'template' => $this->musicCount->getTemplate(),
            ],
            $this->musicCount->getData(date: $today, systems: $this->systems),
          ],
          [
            $this->cache::Tags   => [
              'gate',
              'gate.widgets',
              'gate.widgets.musicCounts',
              'games/'.$today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
          ]
        );

        [$topPlayersHash, $topPlayersData] = $this->cache->load(
          'gate.today.topPlayers.'.$today->format('Y-m-d'),
          function () use ($today, $musicGameIds) {
              $this->topPlayerSkills->setGameIds($musicGameIds);
              return [
                $this->topPlayerSkills->getHash(date: $today, systems: $this->systems),
                [
                  'data'     => $this->topPlayerSkills->getData(date: $today, systems: $this->systems),
                  'template' => $this->topPlayerSkills->getTemplate(),
                ],
              ];
          },
          [
            $this->cache::Tags   => [
              'gate',
              'gate.widgets',
              'gate.widgets.topPlayers',
              'games/'.$today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
          ]
        );

        return $this->view(
          'gate/screens/todayHighlightsRtsp',
          [
            'settings'    => $this->getSettings(),
            'screenHash' => md5($highlightsHash.$musicHash.$topPlayersHash),
            'widgets'    => [
              'highlights' => $highlightsData,
              'music'      => $musicData,
              'skills'     => $topPlayersData,
            ],
            'addJs'       => ['gate/todayHighlightsRtsp.js'],
            'addCss'      => ['gate/todayHighlightsRtsp.css'],
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