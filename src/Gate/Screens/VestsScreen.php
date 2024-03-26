<?php

namespace App\Gate\Screens;

use App\Api\Response\ErrorDto;
use App\GameModels\Vest;
use App\Gate\Settings\VestsSettings;
use Psr\Http\Message\ResponseInterface;

class VestsScreen extends GateScreen
{

	private VestsSettings $settings;

	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return lang('Vesty', context: 'gate-screens');
	}

	public static function getDescription(): string {
		return lang('Obrazovka zobrazující přiřazené vesty před hrou.', context: 'gate-screens-description');
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
		$reloadTimer = $this->getSettings()->time - (time() - $game->start?->getTimestamp()) + 2;

		return $this
			->view(
				'gate/screens/vests',
				[
					'game' => $game,
					'vests' => Vest::getForSystem($game::SYSTEM),
				]
			)
			->withHeader('X-Reload-Time', $reloadTimer);
	}

	public function getSettings(): VestsSettings {
		if (!isset($this->settings)) {
			$this->settings = new VestsSettings();
		}
		return $this->settings;
	}

	public function setSettings(VestsSettings $settings): VestsScreen {
		$this->settings = $settings;
		return $this;
	}
}