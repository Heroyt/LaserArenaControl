<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Core\App;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\Gate\Screens\GateScreen;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 *
 */
class ResultsScreen extends GateScreen implements ResultsScreenInterface
{
	use WithResultsSettings;

	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return lang('Výsledky ze hry', context: 'gate-screens');
	}

	public static function getDescription(): string {
		return lang(
			         'Obrazovka zobrazující výsledky z her. Automaticky vybírá zobrazení podle herního módu.',
			context: 'gate-screens-description'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function run(): ResponseInterface {
		$game = $this->getGame();

		if (!isset($game)) {
			return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
		}

		// Find correct screen based on game
		if (($mode = $game->getMode()) !== null && $mode instanceof CustomResultsMode && class_exists(
				$screenClass = $mode->getCustomGateScreen()
			)) {
			/** @var (ResultsScreenInterface&GateScreen)|null $screen */
			$screen = App::getServiceByType($screenClass);
		}

		try {
			// Default to basic rankable
			/** @var ResultsScreenInterface&GateScreen $screen */
			$screen ??= match ($game::SYSTEM) {
				'evo5', 'evo6' => App::getService('gate.screens.results.lasermaxxRankable'),
				default        => throw new Exception('Cannot find results screen for system ' . $game::SYSTEM),
			};
		} catch (Throwable $e) {
			return $this->respond(new ErrorDto('An error occured', exception: $e), 500);
		}

		$screen->setGame($game)->setSettings($this->getSettings());

		return $screen->run();
	}
}