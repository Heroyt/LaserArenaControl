<?php

namespace App\Gate\Screens\Results;

use App\Gate\Screens\GateScreen;

/**
 *
 */
abstract class AbstractResultsScreen extends GateScreen implements ResultsScreenInterface
{
	use WithResultsSettings;

	public function isActive() : bool {
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
}