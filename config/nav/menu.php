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
	],
	[
		'name' => lang('Games'),
		'route' => 'games-list',
		'icon' => 'fas fa-list',
	],
	[
		'name' => lang('Print'),
		'route' => 'results',
		'icon' => 'fas fa-print',
	],
	[
		'name' => lang('Gate'),
		'route' => 'gate',
		'icon' => 'fas fa-display',
	],
];

$featureConfig = App::getServiceByType(FeatureConfig::class);

if ($featureConfig->isFeatureEnabled('tournaments')) {
	$menu['tournaments'] = [
		'name' => lang('Turnaje'),
		'icon' => 'fa-solid fa-trophy',
		'route' => 'tournaments',
	];
}

$menu['settings'] = [
	'name' => lang('Settings'),
	'route' => 'settings',
	'icon' => 'fas fa-cog',
	'children' => [
		[
			'name' => lang('General'),
			'route' => 'settings',
		],
		[
			'name' => lang('Gate'),
			'route' => 'settings-gate',
		],
		[
			'name' => lang('Vests'),
			'route' => 'settings-vests',
		],
		[
			'name' => lang('Game modes'),
			'route' => 'settings-modes',
		],
		[
			'name' => lang('Print'),
			'route' => 'settings-print',
		],
		[
			'name' => lang('Music'),
			'route' => 'settings-music',
		],
	],
];

if ($featureConfig->isFeatureEnabled('groups')) {
	$menu['settings']['children'][] = [
		'name' => lang('Skupiny'),
		'route' => 'settings-groups',
	];
}

$menu['settings']['children'][] = [
	'name' => lang('Cache'),
	'route' => 'settings-cache',
];

foreach (App::getContainer()->findByType(MenuExtensionInterface::class) as $name) {
	/** @var MenuExtensionInterface $extension */
	$extension = App::getService($name);
	$extension->extend($menu);
}

return $menu;