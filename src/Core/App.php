<?php

namespace App\Core;

use Lsr\Caching\Cache;
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
            'tags'   => ['core', 'core.menu'],
            'expire' => '30 days',
          ]
        );
    }

    /**
     * @return non-empty-string
     */
    public static function getShortLanguageCode() : string {
        return self::getInstance()->translations->getLang();
    }
}
