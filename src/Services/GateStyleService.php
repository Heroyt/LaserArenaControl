<?php

namespace App\Services;

use Lsr\Core\App;

class GateStyleService
{

	public static function getGateBackgroundUrl() : string {
		return App::getUrl().str_replace(ROOT, '', self::getGateBackgroundPath());
	}

	public static function getGateBackgroundPath() : string {
		$image = UPLOAD_DIR.'/gate';
		if (file_exists($image.'.png')) {
			return $image.'.png';
		}
		if (file_exists($image.'.jpg')) {
			return $image.'.jpg';
		}
		if (file_exists($image.'.jpeg')) {
			return $image.'.jpeg';
		}
		$image = ASSETS_DIR.'/images/gate/bg';
		if (file_exists($image.'.png')) {
			return $image.'.png';
		}
		if (file_exists($image.'.jpg')) {
			return $image.'.jpg';
		}
		if (file_exists($image.'.jpeg')) {
			return $image.'.jpeg';
		}
		return '';
	}

}