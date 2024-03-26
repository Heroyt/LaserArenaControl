<?php

namespace App\Gate\Settings;

use App\Core\App;
use Lsr\Core\Config;
use Lsr\Core\Constants;

class ResultsSettings
{

	/** @var int Maximum time (in seconds) of how long the vests screen should remain active */
	public int $time;

	public function __construct(
		?int $time = null
	) {
		/** @var Config $config */
		$config = App::getService('config');
		// Get default value from config or deprecated constant
		$this->time = (int)($time ?? $config->getConfig('ENV')['GAME_RESULTS_TIME'] ?? Constants::GAME_RESULTS_TIME);
	}

}