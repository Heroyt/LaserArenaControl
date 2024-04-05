<?php

namespace App\Gate\Settings;

use App\Models\DataObjects\Image;

/**
 *
 */
readonly class ImageSettings extends GateSettings
{

	public function __construct(
		public ?Image          $image = null,
		public ImageScreenType $screenType = ImageScreenType::CENTER,
		public AnimationType   $animationType = AnimationType::FADE,
	) {}

}