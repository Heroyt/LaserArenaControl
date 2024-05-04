<?php

namespace App\Gate\Screens\DayStats\WithRtsp;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\RtspSettings;
use App\Models\MusicMode;
use App\Services\GameHighlight\GameHighlightService;
use DateTimeImmutable;
use Dibi\Row;
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

        // Get highligths
        $highlights = $this->highlightService->getHighlightsDataForDay($today);
        $data = [];
        foreach ($highlights as $highlight) {
            $data[] = $highlight->code.$highlight->description;
        }

        // Get other stats
        $query = GameFactory::queryGames(true, $today, ['id_music']);
        if (count($this->systems) > 0) {
            $query->where('system IN %in', $this->systems);
        }
        /** @var array<string,Row[]> $games */
        $games = $query->fetchAssoc('system|id_game', cache: false);
        /** @var array<string, int[]> $gameIds */
        $gameIds = [];
        $gameCount = 0;
        $musicCounts = [];

        foreach ($games as $system => $g) {
            /** @var array<int, Row> $g */
            $gameIds[$system] = array_keys($g);
            $gameCount += count($g);
            foreach ($g as $game) {
                if (isset($game->id_music)) {
                    $musicCounts[$game->id_music] ??= 0;
                    $musicCounts[$game->id_music]++;
                }
            }
        }

        arsort($musicCounts);
        $data[] = $musicCounts;
        $musicModes = MusicMode::getAll();

        $topPlayers = [];
        if (!empty($gameIds)) {
            $q = PlayerFactory::queryPlayers($gameIds);
            $topScores = $q->orderBy('[skill]')->desc()->limit(10)->fetchAssoc('name', cache: false);
            if (!empty($topScores)) {
                $count = 0;
                foreach ($topScores as $score) {
                    $topPlayers[] = PlayerFactory::getById(
                      (int) $score->id_player,
                      ['system' => (string) $score->system]
                    );
                }
            }
        }

        return $this->view(
          'gate/screens/todayHighlightsRtsp',
          [
            'settings'    => $this->getSettings(),
            'topPlayers'  => $topPlayers,
            'screenHash'  => md5(json_encode($data, JSON_THROW_ON_ERROR)),
            'musicModes'  => $musicModes,
            'musicCounts' => $musicCounts,
            'highlights'  => $highlights,
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