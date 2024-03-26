<?php

namespace App\Gate\Screens\Results;

use App\Gate\Settings\ResultsSettings;

/**
 *
 */
interface ResultsScreenInterface
{

	/**
	 * Set screen settings.
	 *
	 * @param ResultsSettings $settings
	 *
	 * @return $this
	 */
	public function setSettings(ResultsSettings $settings): static;

	/**
	 * Return a settings DTO.
	 *
	 * If settings was not already set, it should create a new instance with default values.
	 *
	 * @return ResultsSettings
	 */
	public function getSettings(): ResultsSettings;

}