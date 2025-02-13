<?php

/**
 * @file  config/nav/menu.php
 * @brief Definition of the main menu items
 */

use App\Core\App;
use App\Gate\Models\GateType;
use App\Services\FeatureConfig;
use App\Services\FontAwesomeManager;
use LAC\Modules\Core\MenuExtensionInterface;

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

$menu = [
  [
    'name' => lang('Nová hra', context: 'pageTitles'),
    'route' => 'dashboard',
    'icon'  => $fontawesome->solid('plus'),
    'order' => 0,
  ],
  [
    'name' => lang('Hry', context: 'pageTitles'),
    'route' => 'games-list',
    'icon'  => $fontawesome->solid('list'),
    'order' => 10,
  ],
  [
    'name' => lang('Tisk', context: 'pageTitles'),
    'route' => 'results',
    'icon'  => $fontawesome->solid('print'),
    'order' => 20,
  ],
  'gate' => [
    'name' => lang('Výsledková tabule', context: 'pageTitles'),
    'route'    => 'gate',
    'icon'     => $fontawesome->solid('display'),
    'order'    => 30,
    'children' => [],
  ],
  'players' => [
    'name' => lang('Registrovaní hráči', context: 'pageTitles'),
    'route'    => 'liga-players',
    'icon'     => $fontawesome->solid('users'),
    'order'    => 40,
    'children' => [],
  ],
];

foreach (GateType::getAll() as $gateType) {
    $menu['gate']['children'][] = [
      'name' => $gateType->name,
      'path' => $gateType->getPath(),
    ];
}

$featureConfig = App::getService('features');
assert($featureConfig instanceof FeatureConfig, 'Invalid service type from DI');

$menu['settings'] = [
  'name'     => lang('Nastavení', context: 'pageTitles'),
  'route'    => 'settings',
  'icon'     => $fontawesome->solid('cog'),
  'order'    => 98,
  'children' => [
    [
      'name' => lang('Obecné', context: 'pageTitles.settings'),
      'route' => 'settings',
      'order' => 0,
    ],
    [
      'name'  => lang('Systémy lasergame', context: 'pageTitles.settings'),
      'route' => 'settings-systems',
      'order' => 10,
    ],
    [
      'name' => lang('Výsledková tabule', context: 'pageTitles.settings'),
      'route' => 'settings-gate',
      'order' => 20,
    ],
    [
      'name' => lang('Herní módy', context: 'pageTitles.settings'),
      'route' => 'settings-modes',
      'order' => 30,
    ],
    [
      'name' => lang('Tisk', context: 'pageTitles.settings'),
      'route' => 'settings-print',
      'order' => 40,
    ],
    [
      'name' => lang('Hudba', context: 'pageTitles.settings'),
      'route' => 'settings-music',
      'order' => 50,
    ],
    [
      'name' => lang('Tipy', context: 'pageTitles.settings'),
      'route' => 'settings-tips',
      'order' => 60,
    ],
  ],
];

if ($featureConfig->isFeatureEnabled('groups')) {
    $menu['settings']['children'][] = [
      'name' => lang('Skupiny', context: 'pageTitles.settings'),
      'route' => 'settings-groups',
      'order' => 60,
    ];
}

$menu['system'] = [
  'name' => lang('Systém', context: 'pageTitles'),
  'route' => 'system',
  'icon' => $fontawesome->solid('server'),
  'order' => 99,
  'children' => [
    [
      'name' => lang('Mezipaměť', context: 'pageTitles.settings'),
      'route' => 'settings-cache',
      'order' => 10,
    ],
    [
      'name' => lang('Soft-reset', context: 'pageTitles.system'),
      'route' => 'resetRoadrunnerGet',
      'order' => 20,
    ],
    [
      'name' => lang('Hard-reset', context: 'pageTitles.system'),
      'route' => 'resetDockerGet',
      'order' => 30,
    ],
  ],
];

if ($featureConfig->isFeatureEnabled('rtsp')) {
    $menu['system']['children'][] = [
      'name' => lang('Kamery reset', context: 'pageTitles.system'),
      'route' => 'resetFFMPEGDockerGet',
      'order' => 40,
    ];
}

foreach (App::getContainer()->findByType(MenuExtensionInterface::class) as $name) {
    /** @var MenuExtensionInterface $extension */
    $extension = App::getService($name);
    $extension->extend($menu);
}

return $menu;
