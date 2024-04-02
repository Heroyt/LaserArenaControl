<?php

namespace App\Gate\Screens;

use App\Api\Response\ErrorDto;
use App\GameModels\Vest;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\VestsSettings;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<VestsSettings>
 */
class VestsScreen extends GateScreen implements WithSettings
{

	private VestsSettings $settings;

	public function isActive() : bool {
		return $this->getReloadTimer() > 0;
	}

	/**
	 * @inheritDoc
	 */
	public static function getName() : string {
		return lang('Vesty', context: 'gate-screens');
	}

	public static function getDescription() : string {
		return lang('Obrazovka zobrazující přiřazené vesty před hrou.', context: 'gate-screens-description');
	}

	/**
	 * @inheritDoc
	 */
	public static function getDiKey() : string {
		return 'gate.screens.vests';
	}

	/**
	 * @inheritDoc
	 */
	public function run() : ResponseInterface {
		$game = $this->getGame();

		if (!isset($game)) {
			return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
		}

		// Calculates how much longer should the screen remain active before reloading

		return $this
			->view(
				'gate/screens/vests',
				[
					'game' => $game,
					'vests' => Vest::getForSystem($game::SYSTEM),
					'addJs'  => ['gate/vests.js'],
					'addCss' => ['gate/vests.css'],
				]
			)
			->withHeader('X-Reload-Time', $this->getReloadTimer());
	}

	public function getSettings() : VestsSettings {
		if (!isset($this->settings)) {
			$this->settings = new VestsSettings();
		}
		return $this->settings;
	}

	public function setSettings(GateSettings $settings) : static {
		if (!($settings instanceof VestsSettings)) {
			throw new InvalidArgumentException('$settings must be an instance of '.VestsSettings::class.', '.$settings::class.' provided.');
		}
		$this->settings = $settings;
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public static function getSettingsForm() : string {
		return 'gate/settings/vests.latte';
	}

	/**
	 * @inheritDoc
	 */
	public static function buildSettingsFromForm(array $data) : GateSettings {
		return new VestsSettings(isset($data['time']) ? (int) $data['time'] : null);
	}

	private function getReloadTimer() : int {
		return $this->getSettings()->time - (time() - ($this->getGame()?->start?->getTimestamp() ?? 0)) + 2;
	}

}