<?php

namespace App\Gate\Screens\Results;

use App\Gate\Settings\ResultsSettings;

/**
 *
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

	public function setSettings(ResultsSettings $settings): static {
		$this->settings = $settings;
		return $this;
	}
}