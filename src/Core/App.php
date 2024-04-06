<?php

namespace App\Core;

use Lsr\Core\Caching\Cache;

class App extends \Lsr\Core\App
{

	public static function getMenu(string $type = 'menu') : array {
		/** @var Cache $cache */
		$cache = self::getService('cache');
		$uri = self::getRequest()->getUri();
		$host = $uri->getScheme().'_'.$uri->getHost().':'.$uri->getPort();
		return $cache->load('menu.'.$type.'.items.'.self::getShortLanguageCode().'.'.$host,
			fn() => self::getService('menu.builder')->getMenu($type),
			                  [
			                    $cache::Tags   => ['core', 'core.menu'],
			                    $cache::Expire => '30 days',
		                    ]);
	}

}