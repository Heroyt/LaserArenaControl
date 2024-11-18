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
    'route' => 'public',
    'icon'  => $fontawesome->solid('plus'),
    'order' => 0,
  ],
  [
    'name' => lang('Hudební módy', context: 'pageTitles'),
    'route' => 'public-music',
    'icon'  => $fontawesome->solid('music'),
    'order' => 10,
  ],
  [
    'name' => lang('Laser Liga', context: 'pageTitles'),
    'route' => 'public-liga',
    'icon'  => $fontawesome->solid('user'),
    'order' => 30,
  ],
];

//$featureConfig = App::getService('features');
//assert($featureConfig instanceof FeatureConfig, 'Invalid service type from DI');
//
//if ($featureConfig->isFeatureEnabled('groups')) {
//}

return $menu;
