<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Evo5\GameModes\M100Naboju;
use App\Gate\Screens\WithGameQR;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class LaserMaxx100NabojuResultsScreen extends AbstractResultsScreen
{
	use WithGameQR;

	/**
	 * @inheritDoc
	 */
	public static function getName() : string {
		return lang('LaserMaxx výsledky z módu 100 nábojů', context: 'gate-screens');
	}

	public static function getDescription() : string {
		return lang('Obrazovka zobrazující výsledky LaserMaxx z módu 100 nábojů.', context: 'gate-screens-description');
	}

	/**
	 * @inheritDoc
	 */
	public static function getDiKey() : string {
		return 'gate.screens.results.lasermaxx.100naboju';
	}

	public function isActive() : bool {
		try {
			return parent::isActive() && $this->getGame()?->getMode() instanceof M100Naboju;
		} catch (GameModeNotFoundException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run() : ResponseInterface {
		$game = $this->getGame();

		if (!isset($game)) {
			return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
		}

		return $this->view(
			'gate/screens/results/lasermaxx100naboju',
			[
				'game'   => $game,
				'qr'     => $this->getQR($game),
				'mode'   => $game->getMode(),
				'addCss' => ['gate/results.css'],
			]
		)
		            ->withHeader('X-Reload-Time', (string) $this->getReloadTimer());
	}
}