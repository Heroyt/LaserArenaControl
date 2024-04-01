<?php

namespace App\Gate\Screens\Results;

use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ResultsSettings;

/**
 *
 */
abstract class AbstractResultsScreen extends GateScreen implements ResultsScreenInterface
{
	use WithResultsSettings;

	public function isActive() : bool {
		bdump($this->getReloadTimer());
		return $this->getReloadTimer() > 0;
	}

	/**
	 * Get the number of seconds before this screen should be reloaded (inactive).
	 *
	 * @return int Seconds before reload
	 */
	protected function getReloadTimer() : int {
		return $this->getSettings()->time - (time() - ($this->getGame()?->end?->getTimestamp() ?? 0)) + 2;
	}

	/**
	 * @inheritDoc
	 */
	public static function getSettingsForm() : string {
		return 'gate/settings/results.latte';
	}

	/**
	 * @inheritDoc
	 */
	public static function buildSettingsFromForm(array $data) : GateSettings {
		return new ResultsSettings(isset($data['time']) ? (int) $data['time'] : null);
	}
}