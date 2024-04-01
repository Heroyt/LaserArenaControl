<?php

namespace App\Gate\Screens\Results;

use App\Gate\Screens\WithSettings;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ResultsSettings;
use InvalidArgumentException;

/**
 * @implements WithSettings<ResultsSettings>
 */
trait WithResultsSettings
{
	private ResultsSettings $settings;


	public function getSettings(): ResultsSettings {
		if (!isset($this->settings)) {
			$this->settings = new ResultsSettings();
		}
		return $this->settings;
	}

	public function setSettings(GateSettings $settings) : static {
		if (!($settings instanceof ResultsSettings)) {
			throw new InvalidArgumentException('$settings must be an instance of '.ResultsSettings::class.', '.$settings::class.' provided.');
		}
		$this->settings = $settings;
		return $this;
	}
}