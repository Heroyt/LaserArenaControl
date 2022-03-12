<?php

namespace App\Models\Game\GameModes;

interface CustomResultsMode
{

	/**
	 * Get a template file containing custom results
	 *
	 * @return string Path to template file
	 */
	public function getCustomResultsTemplate() : string;

	/**
	 * Get a template file containing the custom gate results
	 *
	 * @return string Path to template file
	 */
	public function getCustomGateTemplate() : string;

}