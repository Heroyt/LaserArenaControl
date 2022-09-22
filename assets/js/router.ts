import {getLink} from "./functions";

interface PageInfo {
	type: 'GET' | 'POST',
	routeName?: string,
	path: string[]
}

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
		case 'settings-print':
			import(
				/* webpackChunkName: "resultsReload" */
				'./pages/settings/print'
				).then(module => {
				module.default();
			});
			break;
		case 'settings-vests':
			import(
				/* webpackChunkName: "resultsReload" */
				'./pages/settings/vests'
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
		case 'gate':
			import(
				/* webpackChunkName: "gate" */
				'./pages/gate'
				).then(module => {
				module.default();
			});
			break;
		case 'dashboard':
			import(
				/* webpackChunkName: "dashboard" */
				'./pages/newGame'
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
	}
}