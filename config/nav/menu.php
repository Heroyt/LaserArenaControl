<?php
/**
 * @file  config/nav/menu.php
 * @brief Definition of the main menu items
 */

use App\Services\FeatureConfig;
use LAC\Modules\Core\MenuExtensionInterface;
use Lsr\Core\App;

$menu = [
	[
		'name' => lang('New game'),
		'route' => 'dashboard',
		'icon' => 'fa-solid fa-plus',
		'order' => 0,
	],
	[
		'name' => lang('Games'),
		'route' => 'games-list',
		'icon' => 'fas fa-list',
		'order' => 10,
	],
	[
		'name' => lang('Print'),
		'route' => 'results',
		'icon' => 'fas fa-print',
		'order' => 20,
	],
	[
		'name' => lang('Gate'),
		'route' => 'gate',
		'icon' => 'fas fa-display',
		'order' => 30,
	],
];

$featureConfig = App::getServiceByType(FeatureConfig::class);

$menu['settings'] = [
	'name' => lang('Settings'),
	'route' => 'settings',
	'icon' => 'fas fa-cog',
	'order' => 99,
	'children' => [
		[
			'name' => lang('General'),
			'route' => 'settings',
			'order' => 0,
		],
		[
			'name' => lang('Gate'),
			'route' => 'settings-gate',
			'order' => 10,
		],
		[
			'name' => lang('Vests'),
			'route' => 'settings-vests',
			'order' => 20,
		],
		[
			'name' => lang('Game modes'),
			'route' => 'settings-modes',
			'order' => 30,
		],
		[
			'name' => lang('Print'),
			'route' => 'settings-print',
			'order' => 40,
		],
		[
			'name' => lang('Music'),
			'route' => 'settings-music',
			'order' => 50,
		],
		[
			'name' => lang('Cache'),
			'route' => 'settings-cache',
			'order' => 99,
		],
	],
];

if ($featureConfig->isFeatureEnabled('groups')) {
	$menu['settings']['children'][] = [
		'name' => lang('Skupiny'),
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