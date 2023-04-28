import {getLink} from "./functions";
import {PageInfo} from "./interfaces/pageInfo";

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
			import(
				/* webpackChunkName: "settingsVest" */
				'./pages/settings/vests'
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
		case 'settings-tables':
			import(
				/* webpackChunkName: "settingsTables" */
				'./pages/settings/tables'
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
				'./pages/newGame/newGame'
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
		case 'tournament-rozlos':
			import(
				/* webpackChunkName: "tournamentRozlos" */
				'./pages/tournaments/rozlos'
				).then(module => {
				module.default();
			});
			break;
		case 'tournament-play':
		case 'tournament-play-game':
			import(
				/* webpackChunkName: "tournamentPlay" */
				'./pages/tournaments/play'
				).then(module => {
				module.default();
			});
			break;
	}
}