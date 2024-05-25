<?php

namespace App\Core;

use Lsr\Core\Caching\Cache;
use Lsr\Core\Menu\MenuBuilder;

class App extends \Lsr\Core\App
{

    public static function getMenu(string $type = 'menu') : array {
        /** @var Cache $cache */
        $cache = self::getService('cache');
        $uri = self::getInstance()->getRequest()->getUri();
        $host = $uri->getScheme().'_'.$uri->getHost().':'.$uri->getPort();
        return $cache->load(
          'menu.'.$type.'.items.'.self::getInstance()->translations->getLang().'.'.$host,
          function () use ($type) {
              /** @var MenuBuilder $menuBuilder */
              $menuBuilder = self::getService('menu.builder');
              return $menuBuilder->getMenu($type);
          },
          [
            $cache::Tags   => ['core', 'core.menu'],
            $cache::Expire => '30 days',
          ]
        );
    }

    public static function getShortLanguageCode() : string {
        return self::getInstance()->translations->getLang();
    }

}