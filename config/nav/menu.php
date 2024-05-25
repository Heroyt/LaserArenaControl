<?php
/**
 * @file  config/nav/menu.php
 * @brief Definition of the main menu items
 */

use App\Core\App;
use App\Gate\Models\GateType;
use App\Services\FeatureConfig;
use LAC\Modules\Core\MenuExtensionInterface;

$menu = [
  [
    'name' => lang('Nová hra'),
    'route' => 'dashboard',
    'icon'  => 'fa-solid fa-plus',
    'order' => 0,
  ],
  [
    'name' => lang('Hry'),
    'route' => 'games-list',
    'icon'  => 'fas fa-list',
    'order' => 10,
  ],
  [
    'name' => lang('Tisk'),
    'route' => 'results',
    'icon'  => 'fas fa-print',
    'order' => 20,
  ],
  'gate' => [
    'name' => lang('Výsledková tabule'),
    'route'    => 'gate',
    'icon'     => 'fas fa-display',
    'order'    => 30,
    'children' => [],
  ],
];

foreach (GateType::getAll() as $gateType) {
    $menu['gate']['children'][] = [
      'name' => $gateType->name,
      'path' => $gateType->getPath(),
    ];
}

$featureConfig = App::getServiceByType(FeatureConfig::class);

$menu['settings'] = [
  'name'     => lang('Nastavení'),
  'route'    => 'settings',
  'icon'     => 'fas fa-cog',
  'order'    => 99,
  'children' => [
    [
      'name' => lang('Obecné'),
      'route' => 'settings',
      'order' => 0,
    ],
    [
      'name' => lang('Výsledková tabule'),
      'route' => 'settings-gate',
      'order' => 10,
    ],
    [
      'name' => lang('Vesty'),
      'route' => 'settings-vests',
      'order' => 20,
    ],
    [
      'name' => lang('Herní módy'),
      'route' => 'settings-modes',
      'order' => 30,
    ],
    [
      'name' => lang('Tisk'),
      'route' => 'settings-print',
      'order' => 40,
    ],
    [
      'name' => lang('Hudba'),
      'route' => 'settings-music',
      'order' => 50,
    ],
    [
      'name' => lang('Mezipaměť'),
      'route' => 'settings-cache',
      'order' => 99,
    ],
  ],
];

if ($featureConfig->isFeatureEnabled('groups')) {
    $menu['settings']['children'][] = [
      'name'  => lang('Skupiny'),
      'route' => 'settings-groups',
      'order' => 60,
    ];
}

foreach (App::getContainer()->findByType(MenuExtensionInterface::class) as $name) {
    /** @var MenuExtensionInterface $extension */
    $extension = App::getService($name);
    $extension->extend($menu);
}

return $menu;