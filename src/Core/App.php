<?php

namespace App\Core;

use Lsr\Core\Caching\Cache;
use Lsr\Core\Menu\MenuBuilder;

class App extends \Lsr\Core\App
{

	public static function getMenu(string $type = 'menu'): array {
		return self::getServiceByType(Cache::class)
		           ->load(
			           'menu.' . $type . '.items.' . self::getShortLanguageCode(),
			           fn() => self::getServiceByType(MenuBuilder::class)->getMenu($type),
			           [
				           Cache::Tags => ['core', 'core.menu'],
				           Cache::Expire => '30 days',
			           ]
		           );
	}

}