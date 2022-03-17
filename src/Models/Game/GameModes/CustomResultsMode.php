<?php

namespace App\Models\Game\GameModes;

use App\Core\Controller;

interface CustomResultsMode
{

	/**
	 * Get a template file containing custom results
	 *
	 * @return string Path to template file
	 */
	public function getCustomResultsTemplate(Controller $controller) : string;

	/**
	 * Get a template file containing the custom gate results
	 *
	 * @return string Path to template file
	 */
	public function getCustomGateTemplate(Controller $controller) : string;

}