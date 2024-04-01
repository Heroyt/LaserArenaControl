<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Evo5\GameModes\Survival;
use App\Gate\Screens\WithGameQR;
use Psr\Http\Message\ResponseInterface;

class LaserMaxxSurvivalResultsScreen extends AbstractResultsScreen
{
	use WithGameQR;

	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return lang('LaserMaxx výsledky z módu Survival', context: 'gate-screens');
	}

	public static function getDescription(): string {
		return lang('Obrazovka zobrazující výsledky LaserMaxx z módu Survival.', context: 'gate-screens-description');
	}

	public function isActive() : bool {
		try {
			return parent::isActive() && $this->getGame()?->getMode() instanceof Survival;
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

		return $this->view(
			'gate/screens/results/lasermaxxSurvival',
			['game' => $game, 'qr' => $this->getQR($game), 'mode' => $game->getMode(),]
		)
			->withHeader('X-Reload-Time', (string) $this->getReloadTimer());
	}

	/**
	 * @inheritDoc
	 */
	public static function getDiKey() : string {
		return 'gate.screens.results.lasermaxxSurvival';
	}
}