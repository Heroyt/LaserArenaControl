<?php

namespace App\Gate;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Gate\Logic\CustomEventDto;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateType;
use App\Gate\Screens\GateScreen;
use DateTimeImmutable;
use Lsr\Core\Config;
use Lsr\Core\Constants;
use Lsr\Core\Exceptions\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Gate service for getting gate screens.
 */
class Gate
{

	private ?int $tmpResultsTime = null;

	public function __construct(readonly private Config $config,) {}

	/**
	 * Get an active screen for set gate and system.
	 *
	 * @param GateType $gate
	 * @param string   $system Either a system name or 'all'
	 *
	 * @return GateScreen The first valid screen
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function getCurrentScreen(GateType $gate, string $system = 'all') : GateScreen {
		$screens = $gate->getScreens();

		/** @var CustomEventDto|null $customEvent */
		$customEvent = Info::get('gate-event');

		$activeGateType = ScreenTriggerType::DEFAULT;

		$game = $this->getActiveGame($system);
		if (isset($customEvent) && $customEvent->time > time()) {
			$activeGateType = ScreenTriggerType::CUSTOM;
		}
		else {
			if (isset($game)) {
				$activeGateType = match (true) {
					$game->isFinished() => ScreenTriggerType::GAME_ENDED,
					$game->isStarted()  => ScreenTriggerType::GAME_PLAYING,
					default             => ScreenTriggerType::GAME_LOADED,
				};
			}
		}

		$systems = [$system];
		if ($system === 'all') {
			$systems = GameFactory::getSupportedSystems();
		}

		$defaultScreen = null;

		foreach ($screens as $screenModel) {
			if ($screenModel->trigger === $activeGateType && (($activeGateType === ScreenTriggerType::CUSTOM && $screenModel->triggerValue === $customEvent?->event) || $activeGateType !== ScreenTriggerType::CUSTOM)) {
				$screen = $screenModel->getScreen()->setGame($game)->setSystems($systems);

				$settings = $screenModel->getSettings();
				if (isset($settings) && method_exists($screen, 'setSettings')) {
					/** @noinspection PhpParamsInspection */
					$screen->setSettings($settings);
				}

				if ($screen->isActive()) {
					return $screen;
				}
			}
			else {
				if ($screenModel->trigger === ScreenTriggerType::DEFAULT) {
					$defaultScreen = $screenModel->getScreen()->setGame($game)->setSystems($systems);
					$settings = $screenModel->getSettings();
					if (isset($settings) && method_exists($defaultScreen, 'setSettings')) {
						/** @noinspection PhpParamsInspection */
						$defaultScreen->setSettings($settings);
					}
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
	 * @param string $system
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
			return $test;
		}

		$lastGame = GameFactory::getLastGame($system);
		if (isset($lastGame) && $lastGame->end?->getTimestamp() > $maxTime) {
			$maxGame = $lastGame;
			$maxTime = $lastGame->end->getTimestamp();
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

		return $maxGame;
	}

	private function getTmpResultsTime() : int {
		$this->tmpResultsTime ??= (int) ($this->config->getConfig(
			'ENV'
		)['TMP_GAME_RESULTS_TIME'] ?? Constants::TMP_GAME_RESULTS_TIME);
		return $this->tmpResultsTime;
	}
}