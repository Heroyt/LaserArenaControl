<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Gate\Screens\GateScreen;
use Psr\Http\Message\ResponseInterface;

class LaserMaxxCSGOResultsScreen extends GateScreen implements ResultsScreenInterface
{
	use WithResultsSettings;

	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return lang('LaserMaxx výsledky z módu CSGO', context: 'gate-screens');
	}

	public static function getDescription(): string {
		return lang('Obrazovka zobrazující výsledky LaserMaxx z módu CSGO.', context: 'gate-screens-description');
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

		return $this->view('gate/screens/results/lasermaxxCSGO', ['game' => $game,])
		            ->withHeader('X-Reload-Time', (string)$reloadTimer);
	}
}