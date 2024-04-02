<?php

namespace App\Gate\Settings;

use App\Gate\Models\GateScreenModel;

/**
 *
 */
readonly class TimerSettings extends GateSettings
{

	/**
	 * @param GateScreenModel[] $children Child screens to rotate
	 * @param int               $timer    Timer to swap screens in seconds
	 */
	public function __construct(
		public array $children = [],
		public int   $timer = 60,
	) {}

}