<?php

namespace App\Core;

use Lsr\Core\Caching\Cache;
use Lsr\Core\Menu\MenuBuilder;

class App extends \Lsr\Core\App
{

	public static function getMenu(string $type = 'menu') : array {
		/** @var Cache $cache */
		$cache = self::getService('cache');
		return $cache->load(
			'menu.'.$type.'.items.'.self::getShortLanguageCode(),
			fn() => self::getServiceByType(MenuBuilder::class)->getMenu($type),
			[
				$cache::Tags   => ['core', 'core.menu'],
				$cache::Expire => '30 days',
			]
		);
	}

}