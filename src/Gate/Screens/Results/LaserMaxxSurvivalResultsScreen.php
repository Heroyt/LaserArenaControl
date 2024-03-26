<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithGameQR;
use Psr\Http\Message\ResponseInterface;

class LaserMaxxSurvivalResultsScreen extends GateScreen implements ResultsScreenInterface
{
	use WithResultsSettings;
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

	/**
	 * @inheritDoc
	 */
	public function run(): ResponseInterface {
		$game = $this->getGame();

		if (!isset($game)) {
			return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
		}

		// Calculates how much longer should the screen remain active before reloading
		$reloadTimer = $this->getSettings()->time - (time() - $game->end?->getTimestamp()) + 2;

		return $this->view(
			'gate/screens/results/lasermaxxSurvival',
			['game' => $game, 'qr' => $this->getQR($game), 'mode' => $game->getMode(),]
		)
		            ->withHeader('X-Reload-Time', (string)$reloadTimer);
	}
}