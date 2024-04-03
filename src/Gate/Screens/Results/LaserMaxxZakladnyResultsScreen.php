<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Evo5\GameModes\Zakladny;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class LaserMaxxZakladnyResultsScreen extends AbstractResultsScreen
{
	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return lang('LaserMaxx výsledky z módu Základny', context: 'gate-screens');
	}

	public static function getDescription(): string {
		return lang('Obrazovka zobrazující výsledky LaserMaxx z módu Základny.', context: 'gate-screens-description');
	}

	/**
	 * @inheritDoc
	 */
	public static function getDiKey() : string {
		return 'gate.screens.results.lasermaxx.zakladny';
	}

	public function isActive() : bool {
		try {
			return parent::isActive() && $this->getGame()?->getMode() instanceof Zakladny;
		} catch (GameModeNotFoundException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run(): ResponseInterface {
		$game = $this->getGame();

		if (!isset($game)) {
			return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
		}

		if ($this->reloadTime < 0) {
			$this->setReloadTime($this->getReloadTimer());
		}

		return $this->view(
			'gate/screens/results/lasermaxxZakladny',
			[
				'game'   => $game,
				'mode'   => $game->getMode(),
				'addCss' => ['gate/resultsZakladny.css'],
			]
		);
	}
}