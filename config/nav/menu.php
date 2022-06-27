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
		'name'  => lang('Gate'),
		'route' => 'gate',
		'icon'  => 'fas fa-display',
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
				'name'  => lang('Gate'),
				'route' => 'settings-gate',
			],
			[
				'name'  => lang('Vests'),
				'route' => 'settings-vests',
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