<?php

namespace App\Gate\Settings;

/**
 *
 */
readonly class YoutubeSettings extends GateSettings
{

	public function __construct(
		public string          $url,
		public ImageScreenType $screenType = ImageScreenType::CENTER,
		public AnimationType   $animationType = AnimationType::FADE,
	) {}

}