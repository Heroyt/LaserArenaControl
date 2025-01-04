<?php

namespace App\Gate;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Gate\Logic\CustomEventDto;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateType;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\ReloadTimerInterface;
use App\Gate\Screens\WithSettings;
use DateTimeImmutable;
use Lsr\Core\Config;
use Lsr\Core\Constants;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Gate service for getting gate screens.
 */
class Gate
{
    public const string MANUAL_RESULTS_GAME_META = 'manual-gate';

    private ?int $tmpResultsTime = null;

    public function __construct(readonly private Config $config) {}

    /**
     * Get an active screen for set gate and system.
     *
     * @param  GateType  $gate
     * @param  string  $system  Either a system name or 'all'
     *
     * @return GateScreen The first valid screen
     * @throws Throwable
     * @throws ValidationException
     */
    public function getCurrentScreen(GateType $gate, string $system = 'all') : GateScreen {
        $screens = $gate->screens;

        /** @var CustomEventDto|null $customEvent */
        $customEvent = Info::get('gate-event');

        $activeGateType = ScreenTriggerType::DEFAULT;

        $game = $this->getActiveGame($system);
        if (isset($customEvent) && $customEvent->time > time()) {
            $activeGateType = ScreenTriggerType::CUSTOM;
        }
        else if (isset($game)) {
            $isManual = $game->getMeta()[$this::MANUAL_RESULTS_GAME_META] ?? false;
            $activeGateType = match (true) {
                $isManual => ScreenTriggerType::RESULTS_MANUAL,
                $game->isFinished() => ScreenTriggerType::GAME_ENDED,
                $game->isStarted()  => ScreenTriggerType::GAME_PLAYING,
                default             => ScreenTriggerType::GAME_LOADED,
            };
        }

        $systems = [$system];
        if ($system === 'all') {
            $systems = GameFactory::getSupportedSystems();
        }

        $defaultScreen = null;

        foreach ($screens as $screenModel) {
            if (
              $screenModel->trigger === $activeGateType
              && (
                (
                  $activeGateType === ScreenTriggerType::CUSTOM
                  && $screenModel->triggerValue === $customEvent?->event
                )
                || $activeGateType !== ScreenTriggerType::CUSTOM
              )
            ) {
                $screen = $screenModel->getScreen()
                                      ->setGame($game)
                                      ->setSystems($systems);

                if ($activeGateType === ScreenTriggerType::CUSTOM) {
                    $screen->setTriggerEvent($customEvent);
                    if ($screen instanceof ReloadTimerInterface && isset($customEvent)) {
                        $screen->setReloadTime(time() - $customEvent->time);
                    }
                }

                $settings = $screenModel->getSettings();
                if (isset($settings) && $screen instanceof WithSettings) {
                    $screen->setSettings($settings);
                }

                if ($screen->isActive()) {
                    return $screen;
                }
            }
            else if ($screenModel->trigger === ScreenTriggerType::DEFAULT) {
                $defaultScreen = $screenModel->getScreen()->setGame($game)->setSystems($systems);
                $settings = $screenModel->getSettings();
                if (isset($settings) && method_exists($defaultScreen, 'setSettings')) {
                    $defaultScreen->setSettings($settings);
                }
            }
        }

        // No active screen was found - use default if possible
        if (isset($defaultScreen)) {
            return $defaultScreen;
        }

        throw new RuntimeException('No valid screen found to display.');
    }

    /**
     * Get current active game for system
     *
     * @param  string  $system
     *
     * @return Game|null
     * @throws Throwable
     */
    public function getActiveGame(string $system = 'all') : ?Game {
        $systems = [$system];
        if ($system === 'all') {
            $systems = GameFactory::getSupportedSystems();
        }

        $now = time();
        $maxGame = null;
        $maxTime = 0;

        /** @var Game|null $test */
        $test = Info::get('gate-game');
        /** @var int $gateTime */
        $gateTime = Info::get('gate-time', $now);
        if (isset($test) && ($now - $gateTime) <= $this->getTmpResultsTime()) {
            // Set the correct (fake) end time
            $test->end = (new DateTimeImmutable())->setTimestamp($gateTime);
            $test->setMetaValue($this::MANUAL_RESULTS_GAME_META, true);
            return $test;
        }

        foreach ($systems as $checkSystem) {
            /** @var Game|null $startedSystem */
            $startedSystem = Info::get($checkSystem.'-game-started');
            if (isset($startedSystem) && $startedSystem->start->getTimestamp() > $maxTime) {
                $maxGame = $startedSystem;
                $maxTime = $startedSystem->start->getTimestamp();
            }

            /** @var Game|null $loadedSystem */
            $loadedSystem = Info::get($checkSystem.'-game-loaded');
            if (isset($loadedSystem) && $loadedSystem->fileTime?->getTimestamp() > $maxTime) {
                $maxGame = $loadedSystem;
                $maxTime = $loadedSystem->fileTime?->getTimestamp();
            }
        }

        $query = $system === 'all' ? GameFactory::queryGames(true) : GameFactory::queryGamesSystem($system, true);
        $row = $query->where('end > %dt', $maxTime)->orderBy('end')->desc()->fetch();
        if (isset($row) && $row->end?->getTimestamp() > $maxTime) {
            $maxGame = GameFactory::getById($row->id_game, ['system' => $row->system]);
        }

        return $maxGame;
    }

    private function getTmpResultsTime() : int {
        $this->tmpResultsTime ??= (int) ($this->config->getConfig(
          'ENV'
        )['TMP_GAME_RESULTS_TIME'] ?? Constants::TMP_GAME_RESULTS_TIME);
        return $this->tmpResultsTime;
    }
}
