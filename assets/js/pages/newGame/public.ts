import Game from '../../game/game';
import {validateForm} from './validate';
import {startLoading, stopLoading} from '../../loaders';
import {sendPreparedGamePublic} from '../../api/endpoints/preparedGames';
import {initGroupAutocomplete} from '../../components/groupSearch';
import {triggerNotificationError} from '../../includes/notifications';

export default function initNewGamePage() {
	const form = document.getElementById('new-game-content') as HTMLFormElement;

	// Toggle offcanvas
	// const helpOffcanvasElement = document.getElementById('help') as HTMLDivElement;
	// const helpOffcanvas = new Offcanvas(helpOffcanvasElement, {backdrop: true});
	// (document.querySelectorAll('.trigger-help') as NodeListOf<HTMLButtonElement>).forEach(btn => {
	// 	btn.addEventListener('click', () => {
	// 		helpOffcanvas.show();
	// 	});
	// });

	// Autosave to local storage
	form.addEventListener('update', () => {
		const data = game.export();
		localStorage.setItem('new-game-data-public', JSON.stringify(data));
	});

	const game = new Game();

	const localData = localStorage.getItem('new-game-data-public');
	if (gameData) {
		game.import(gameData);
	} else if (localData) {
		game.import(JSON.parse(localData));
	}

	// Send form via ajax
	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(form);
		data.set('action', 'load');

		if (!validateForm(data, game)) {
			return;
		}

		startLoading();
		sendPreparedGamePublic(game.export())
			.then(() => {
				stopLoading(true);
			})
			.catch(e => {
				triggerNotificationError(e);
				stopLoading(false);
			});
	});

	import(
		/* webpackChunkName: "newGame_userSearch" */
		'./userSearch'
		)
		.then(module => {
				const userSearch = new module.default(true);
				userSearch.init();
				userSearch.initGame(game);
			},
		);

	if (game.$newGroupName) {
		game.$newGroupName.addEventListener('input', () => {
			game.$group.value = 'new-custom';
			form.dispatchEvent(new Event('update'));
		});
		initGroupAutocomplete(game.$newGroupName, (name, id) => {
			game.$newGroupName.value = name;
			game.$group.value = id.toString();
			form.dispatchEvent(new Event('update'));
		});
	}

	const systemSelect = document.getElementById('systems-select') as HTMLSelectElement;
	if (systemSelect) {
		systemSelect.addEventListener('change', () => {
			const systemId = parseInt(systemSelect.value);
			const params = new URLSearchParams(window.location.search);
			params.append('system', systemId.toString());
			window.location.href = window.location.href.split('?')[0] + '?' + params.toString();
		});
	}
}