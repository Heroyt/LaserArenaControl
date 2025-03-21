import {PageInfo} from './interfaces/pageInfo';
import {getLink} from './includes/frameworkFunctions';

const resultsReloadPages: { [index: string]: string[] } = {
	'games-list': [],
	'results': ['results'],
	'results-game': ['results'],
};

export default function route(pageInfo: PageInfo): void {
	if (Object.keys(resultsReloadPages).includes(pageInfo.routeName ?? '')) {
		import(
			/* webpackChunkName: "resultsReload" */
			'./pages/resultsReload'
			).then(module => {
			const link = resultsReloadPages[pageInfo.routeName].length === 0 ? null : getLink(resultsReloadPages[pageInfo.routeName]);
			module.default(link);
		});
	}
	switch (pageInfo.routeName ?? '') {
		case 'settings':
			import(
				/* webpackChunkName: "settingsGeneral" */
				'./pages/settings/general'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-print':
			import(
				/* webpackChunkName: "settingsPrint" */
				'./pages/settings/print'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-gate':
			import(
				/* webpackChunkName: "settingsGate" */
				'./pages/settings/gate'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-vests':
		case 'settings-systems':
			import(
				/* webpackChunkName: "settingsSystems" */
				'./pages/settings/systems'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-cache':
			import(
				/* webpackChunkName: "settingsCache" */
				'./pages/settings/cache'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-groups':
			import(
				/* webpackChunkName: "settingsGroups" */
				'./pages/settings/groups'
				).then(module => {
				module.default();
			});
			break;
		case 'games-list':
			import(
				/* webpackChunkName: "gamesList" */
				'./pages/gamesList'
				).then(module => {
				module.default();
			});
			break;
		case 'dashboard':
			import(
				/* webpackChunkName: "dashboard" */
				'./pages/newGame/newGame'
				).then(module => {
				module.default();
			});
			break;
		case 'liga-players':
			import(
				/* webpackChunkName: "players" */
				'./pages/players'
				).then(module => {
				module.default();
			});
			break;
		case 'public':
			import(
				/* webpackChunkName: "public" */
				'./pages/newGame/public'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-music':
			import(
				/* webpackChunkName: "musicSettings" */
				'./pages/settings/music'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-modes':
			import(
				/* webpackChunkName: "modesSettings" */
				'./pages/settings/modes'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-tips':
			import(
				/* webpackChunkName: "tipsSettings" */
				'./pages/settings/tips'
				).then(module => {
				module.default();
			});
			break;
		case 'results':
		case 'results-game':
			import(
				/* webpackChunkName: "results" */
				'./pages/results'
				).then(module => {
				module.default();
			});
			break;
		case 'public-music':
			import(
				/* webpackChunkName: "public-music" */
				'./pages/public/music'
				).then(module => {
				module.default();
			});
			break;
		case 'public-liga':
		case 'public-liga-post':
			import(
				/* webpackChunkName: "public-laserliga" */
				'./pages/public/laserliga'
				).then(module => {
				module.default();
			});
	}

	if ((pageInfo.routeName ?? '').startsWith('gate')) {
		import(
			/* webpackChunkName: "gate" */
			'./pages/gate'
			).then(module => {
			module.default();
		});
	}
}
