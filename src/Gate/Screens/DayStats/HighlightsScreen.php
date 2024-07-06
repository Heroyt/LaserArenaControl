<?php

namespace App\Gate\Screens\DayStats;

use App\Core\App;
use App\Gate\Screens\GateScreen;
use App\Gate\Widgets\Highlights;
use App\Gate\Widgets\MusicCount;
use App\Gate\Widgets\TopPlayerSkills;
use DateTimeImmutable;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

/**
 * General screens that shows today stats and best players.
 */
class HighlightsScreen extends GateScreen
{
    public function __construct(
        Latte                                 $latte,
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
    public static function getName(): string {
        return lang('Dnešní zajímavosti z her', domain: 'gate', context: 'screens');
    }

    public static function getDescription(): string {
        return lang(
            'Obrazovka zobrazující zajímavosti z dnešních odehraných her.',
            domain : 'gate',
            context: 'screens.description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey(): string {
        return 'gate.screens.idle.highlights';
    }

    public static function getGroup(): string {
        return lang('Denní statistiky', domain: 'gate', context: 'screens.groups');
    }

    /**
     * @inheritDoc
     */
    public function run(): ResponseInterface {
        $date = (string) App::getInstance()->getRequest()->getGet('date', 'now');
        $today = new DateTimeImmutable($date);

        $this->highlights->refresh();
        $this->musicCount->refresh();
        $this->topPlayerSkills->refresh();

        [$highlightsHash, $highlightsData] = $this->cache->load(
            'gate.today.highlights.' . $today->format('Y-m-d'),
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
              'games/' . $today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
            ]
        );

        [$musicHash, $musicData, $musicGameIds] = $this->cache->load(
            'gate.today.musicCounts.' . $today->format('Y-m-d'),
            fn() => [
              $this->musicCount->getHash(date: $today, systems: $this->systems),
              [
                'data'     => $this->musicCount->getData(date: $today, systems: $this->systems),
                'template' => $this->musicCount->getTemplate(),
              ],
              $this->musicCount->getGameIds(dateFrom: $today, dateTo: $today, systems: $this->systems),
            ],
            [
            $this->cache::Tags   => [
              'gate',
              'gate.widgets',
              'gate.widgets.musicCounts',
              'games/' . $today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
            ]
        );

        [$topPlayersHash, $topPlayersData] = $this->cache->load(
            'gate.today.topPlayers.' . $today->format('Y-m-d'),
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
              'games/' . $today->format('Y-m-d'),
            ],
            $this->cache::Expire => '1 days',
            ]
        );


        return $this->view(
            'gate/screens/todayHighlights',
            [
            'screenHash' => md5($highlightsHash . $musicHash . $topPlayersHash),
            'widgets'    => [
              'highlights' => $highlightsData,
              'music'      => $musicData,
              'skills'     => $topPlayersData,
            ],
            'addJs'       => ['gate/todayHighlights.js'],
            'addCss'      => ['gate/todayHighlights.css'],
            ]
        );
    }
}
