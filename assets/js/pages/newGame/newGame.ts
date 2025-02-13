import Game from '../../game/game';
import EventServerInstance from '../../EventServer';
import {startLoading, stopLoading} from '../../loaders';
import {GameData} from '../../interfaces/gameInterfaces';
import {Offcanvas} from 'bootstrap';
import {isFeatureEnabled} from '../../featureConfig';
import Control, {GameStatus} from './control';
import {gatesStart, gatesStop} from '../../api/endpoints/gates';
import {getLastGames, LoadGameResponse, sendLoadGame} from '../../api/endpoints/games';
import {initPrintButtons} from '../../components/resultsPrinting';
import {lang} from '../../includes/frameworkFunctions';
import {validateForm} from './validate';
import {triggerNotificationError} from '../../includes/notifications';
import {System} from '../../interfaces/system';

declare global {
	const gameData: GameData;
	const system: System;
	const vestIcon: string;
}

function initGatesControls() {
	const gatesStartBtn = document.getElementById('startGates') as HTMLButtonElement | null;
	const gatesStopBtn = document.getElementById('stopGates') as HTMLButtonElement | null;

	if (gatesStartBtn) {
		gatesStartBtn.addEventListener('click', () => {
			startLoading();
			gatesStart()
				.then(() => {
					stopLoading();
				})
				.catch((e) => {
					triggerNotificationError(e);
					stopLoading(false);
				});
		});
	}
	if (gatesStopBtn) {
		gatesStopBtn.addEventListener('click', () => {
			startLoading();
			gatesStop()
				.then(() => {
					stopLoading();
				})
				.catch(e => {
					triggerNotificationError(e);
					stopLoading(false);
				});
		});
	}
}

export default function initNewGamePage() {
	const form = document.getElementById('new-game-content') as HTMLFormElement;

	// Toggle offcanvas
	const helpOffcanvasElement = document.getElementById('help') as HTMLDivElement;
	const helpOffcanvas = new Offcanvas(helpOffcanvasElement, {backdrop: true});
	(document.querySelectorAll('.trigger-help') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		btn.addEventListener('click', () => {
			helpOffcanvas.show();
		});
	});

	const loadBtn = form.querySelector('#loadGame') as HTMLButtonElement;
	const startBtn = form.querySelector('#startGame') as HTMLButtonElement;
	const stopBtn = form.querySelector('#stopGame') as HTMLButtonElement;

	let control: Control | null = null;
	if (isFeatureEnabled('control')) {
		control = new Control(loadBtn, startBtn, stopBtn);
	}

	const gameGroupsWrapper = document.getElementById('game-groups') as HTMLDivElement | undefined;
	const gameGroupTemplate = document.getElementById('new-game-group') as HTMLTemplateElement | undefined;
	const gameGroupsSelect = document.getElementById('group-select') as HTMLSelectElement | undefined;

	if (isFeatureEnabled('gates')) {
		initGatesControls();
	}

	// Autosave to local storage
	form.addEventListener('update', () => {
		const data = game.export();
		localStorage.setItem('new-game-data', JSON.stringify(data));
	});

	// Keyboard shortcuts
	document.addEventListener('keyup', handleKeyboardShortcuts);

	const game = new Game();

	const localData = localStorage.getItem('new-game-data');
	if (gameData) {
		game.import(gameData);
	} else if (localData) {
		game.import(JSON.parse(localData));
	}

	const lastGamesSelect = document.getElementById('last-games') as HTMLSelectElement;

	lastGamesSelect.addEventListener('change', () => {
		const option = lastGamesSelect.querySelector(`option[value="${lastGamesSelect.value}"]`) as HTMLOptionElement;
		if (!option) {
			return;
		}
		game.import(JSON.parse(option.dataset.game));
	});

	document.addEventListener('clear-all', () => {
		lastGamesSelect.value = '';
		if (gameGroupsSelect) {
			gameGroupsSelect.value = '';
		}
	});

	// Send form via ajax
	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(form);

		console.log(e.submitter);

		if (!data.get('action')) {
			data.set('action', (e.submitter as HTMLButtonElement).value);
		}

		if (!validateForm(data, game)) {
			return;
		}

		switch (data.get('action')) {
			case 'load':
				loadGame(data);
				break;
			case 'start':
				if (control) {
					control.startGame(data, loadStartGame);
				}
				break;
			case 'stop':
				if (control) {
					control.stopGame();
				}
				break;
		}
	});

	loadLastGames();

	import(
		/* webpackChunkName: "newGame_userSearch" */
		'./userSearch'
		)
		.then(module => {
				const userSearch = new module.default();
				userSearch.init();
				userSearch.initGame(game);
			},
		);

	if (isFeatureEnabled('groups')) {
		import(
			/* webpackChunkName: "newGame_groups" */
			'./groups'
			)
			.then(module => {
				const groups = new module.default(game, gameGroupsWrapper, gameGroupTemplate, gameGroupsSelect);
				groups.updateGroups();
				EventServerInstance.addEventListener('game-imported', () => groups.updateGroups());

				document.dispatchEvent(new CustomEvent('groups-module-loaded', {detail: groups}));
			});
	}

	if (isFeatureEnabled('preparedGames')) {
		import(
			/* webpackChunkName: "newGame_preparedGames" */
			'./preparedGames'
			)
			.then(module => {
				const preparedGamesWrapper = document.getElementById('game-preparedGames') as HTMLDivElement;
				const preparedGamesBtn = document.getElementById('prepareGame') as HTMLButtonElement;
				new module.default(game, preparedGamesWrapper, preparedGamesBtn);
			});
	}

	EventServerInstance.addEventListener('game-imported', loadLastGames);

	initPrintButtons();

	const systemSelect = document.getElementById('systems-select') as HTMLSelectElement;
	if (systemSelect) {
		systemSelect.addEventListener('change', () => {
			const systemId = parseInt(systemSelect.value);
			const params = new URLSearchParams(window.location.search);
			params.append('system', systemId.toString());
			window.location.href = window.location.href.split('?')[0] + '?' + params.toString();
		});
	}

	function loadLastGames() {
		getLastGames()
			.then(response => {
				response.forEach(game => {
						const test = lastGamesSelect.querySelector(`option[value="${game.code}"]`);
						if (test) {
							console.log('Skip', test);
							return; // Do not add duplicates
						}

					const gameDate = new Date(game.start);

						const option = document.createElement('option');
						option.value = game.code;
						option.dataset.game = JSON.stringify(game);

						const teamCount = Object.keys(game.teams).length;

						if (lastGamesSelect.querySelectorAll('option[data-game]').length >= 10) {
							lastGamesSelect.querySelector('option[data-game]').remove();
						}

						lastGamesSelect.appendChild(option);

						const players = Object.values(game.players).map(player => {
							return player.name;
						}).join(', ');


						option.innerText = `${game.fileNumber} - [${gameDate.getHours().toString().padStart(2, '0')}:${gameDate.getMinutes().toString().padStart(2, '0')}] ${players}`;

						Promise.all([
							lang('%d player', '%d players', game.playerCount, 'game'),
							lang('%d team', '%d teams', teamCount, 'game'),
							lang(game.mode.name, null, 1, 'gameModes'),
						])
							.then(values => {
								const playerString = values[0].replace('%d', game.playerCount.toString());
								const teamString = game.mode.type === 'TEAM' ? values[1].replace('%d', teamCount.toString()) + ', ' : '';
								option.innerText = `${game.fileNumber} - [${gameDate.getHours().toString().padStart(2, '0')}:${gameDate.getMinutes().toString().padStart(2, '0')}] ${values[2]}: ${playerString}, ${teamString} ${players}`;
							});
					},
				);
			})
			.catch((e) => {
				triggerNotificationError(e);
			});
	}

	function loadGame(data: FormData, callback: null | (() => void) = null): void {
		startLoading();
		sendLoadGame(system.id, data)
			.then(response => {
				const mode = handleLoad(response)

				if (control && mode) {
					control.loadGame(mode, callback);
				}
			})
			.catch(e => {
				triggerNotificationError(e);
				stopLoading(false);
			});
	}

	function loadStartGame(data: FormData, callback: null | (() => void) = null): void {
		startLoading();
		sendLoadGame(system.id, data)
			.then(response => {
				const mode = handleLoad(response)

				if (control && mode) {
					control.loadStart(mode, callback);
				}
			})
			.catch(e => {
				triggerNotificationError(e);
				stopLoading(false);
			});
	}

	function handleLoad(response : LoadGameResponse) : string {
		stopLoading();
		console.log(response.values);
		if (!('mode' in response.values) || response.values.mode === '') {
			console.error('Got invalid mode');
			return '';
		}
		const mode = response.values.mode;

		if (typeof response.values.group === 'number' && game.$group.value === 'new-custom') {
			if (game.$group instanceof HTMLSelectElement) {
				let option = game.$group.querySelector<HTMLOptionElement>('option[value="' + game.$group.value + '"]');
				if (!option) {
					option = document.createElement('option');
					option.innerText = response.values.groupName ?? 'Skupina';
				}
				option.value = response.values.group.toString();
			}
			game.$group.value = response.values.group.toString();
		}

		return mode;
	}

	function handleKeyboardShortcuts(e: KeyboardEvent) {
		console.log('keyup', e.key, e.code, e.altKey, e.ctrlKey);
		if (e.target instanceof HTMLElement && (e.target.nodeName.toLowerCase() === 'input' || e.target.nodeName.toLowerCase() === 'textarea')) {
			return;
		}
		switch (e.code) {
			case 'Space': // Space
			case 'Enter': // Enter
				if (!control || control.currentStatus === GameStatus.STANDBY) {
					form.requestSubmit(loadBtn);
				}
				if (control && control.currentStatus === GameStatus.ARMED) {
					form.requestSubmit(startBtn);
				}
				break;
			case 'Backspace': // Backspace
			case 'Delete': // Delete
				if (e.ctrlKey) {
					game.clearAll();
				}
				break;
			case 'KeyV': // v
				(document.getElementById('hide-variations') as HTMLButtonElement)
					.dispatchEvent(new Event('click', {bubbles: true}));
				break;
			case 'KeyH': // h
				helpOffcanvas.toggle();
				break;
		}
	}
}