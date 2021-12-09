<?php
return [
	[
		'name'  => lang('New game'),
		'route' => 'dashboard',
	],
	[
		'name'  => lang('Games'),
		'route' => 'games-list',
		'icon'  => 'fas fa-list',
	],
	[
		'name'  => lang('Print'),
		'route' => 'results',
		'icon'  => 'fas fa-print',
	],
	[
		'name'     => lang('Settings'),
		'route'    => 'settings',
		'icon'     => 'fas fa-cog',
		'children' => [
			[
				'name'  => lang('General'),
				'route' => 'settings',
			],
			[
				'name'  => lang('Game modes'),
				'route' => 'settings-modes',
			],
			[
				'name'  => lang('Print'),
				'route' => 'settings-print',
			],
		],
	],
];