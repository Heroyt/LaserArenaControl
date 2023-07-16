import Game from "../../game/game";
import axios, {AxiosResponse} from "axios";
import {lang} from "../../functions";
import EventServerInstance from "../../EventServer";
import {startLoading, stopLoading} from "../../loaders";
import {GameData} from "../../interfaces/gameInterfaces";
import {Offcanvas} from "bootstrap";
import {isFeatureEnabled} from "../../featureConfig";
import Control, {GameStatus} from "./control";

declare global {
	const gameData: GameData;
	const vestIcon: string;
}

function initGatesControls() {
	const gatesStartBtn = document.getElementById('startGates') as HTMLButtonElement | null;
	const gatesStopBtn = document.getElementById('stopGates') as HTMLButtonElement | null;

	if (gatesStartBtn) {
		gatesStartBtn.addEventListener('click', () => {
			startLoading();
			axios.post('/api/gates/start', {})
				.then(() => {
					stopLoading();
				})
				.catch(() => {
					stopLoading(false);
				});
		});
	}
	if (gatesStopBtn) {
		gatesStopBtn.addEventListener('click', () => {
			startLoading();
			axios.post('/api/gates/stop', {})
				.then(() => {
					stopLoading();
				})
				.catch(() => {
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
		})
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

	// Send form via ajax
	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(form);

		console.log(e.submitter);

		if (!data.get('action')) {
			data.set('action', (e.submitter as HTMLButtonElement).value)
		}

		if (!validateForm(data)) {
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

	loadLastGames();

	import(
		/* webpackChunkName: "newGame_userSearch" */
		'./userSearch'
		)
		.then(module => {
				const userSearch = new module.default();
				userSearch.init()
				userSearch.initGame(game);
			}
		);

	if (isFeatureEnabled('groups')) {
		import(
			/* webpackChunkName: "newGame_groups" */
			'./groups'
			)
			.then(module => {
				const groups = new module.default(game, gameGroupsWrapper, gameGroupTemplate, gameGroupsSelect);
				EventServerInstance.addEventListener('game-imported', groups.updateGroups);

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

	function loadLastGames() {
		axios.get('/api/games', {
			params: {
				limit: 10,
				orderBy: 'start',
				desc: true,
				excludeFinished: true,
				expand: true,
			},
		})
			.then((response: AxiosResponse<GameData[]>) => {
				response.data.forEach(game => {
						const test = lastGamesSelect.querySelector(`option[value="${game.code}"]`);
						if (test) {
							return; // Do not add duplicates
						}

						const gameDate = new Date(game.start.date.replace(' ', 'T'));

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
							lang(game.mode.name, null, 1, 'gameModes')
						])
							.then(values => {
								const playerString = values[0].data.replace('%d', game.playerCount.toString());
								const teamString = game.mode.type === 'TEAM' ? values[1].data.replace('%d', teamCount.toString()) + ', ' : '';
								option.innerText = `${game.fileNumber} - [${gameDate.getHours().toString().padStart(2, '0')}:${gameDate.getMinutes().toString().padStart(2, '0')}] ${values[2].data}: ${playerString}, ${teamString} ${players}`;
							})
					}
				);
			})
			.catch(() => {

			})
	}

	function validateForm(data: FormData): boolean {
		console.log(data.get('action'));
		if (data.get('action') !== 'load') {
			return true;
		}

		const activePlayers = game.getActivePlayers();
		console.log(activePlayers);
		if (activePlayers.length < 2) {
			game.noPlayersTooltip.show();
			return false;
		}

		if (game.getModeType() === 'TEAM') {
			let ok = true;
			const disabledPlayers = activePlayers.filter(player => player.team === null);
			if ((activePlayers.length - disabledPlayers.length) < 2) {
				ok = false;
				disabledPlayers.forEach(player => {
					player.selectTeamTooltip.show();
				});
			}
			if (!ok) {
				return false;
			}
		}

		return true;
	}

	function loadGame(data: FormData, callback: null | (() => void) = null): void {
		startLoading();
		axios.post('/', data)
			.then((response: AxiosResponse<{ status: string, mode?: string }>) => {
				stopLoading();
				if (!response.data.mode || response.data.mode === '') {
					console.error('Got invalid mode');
					return;
				}
				const mode = response.data.mode;

				if (control) {
					control.loadGame(mode, callback);
				}
			})
			.catch(() => {
				stopLoading(false);
			});
	}

	function loadStartGame(data: FormData, callback: null | (() => void) = null): void {
		startLoading();
		axios.post('/', data)
			.then((response: AxiosResponse<{ status: string, mode?: string }>) => {
				stopLoading();
				if (!response.data.mode || response.data.mode === '') {
					console.error('Got invalid mode');
					return;
				}
				const mode = response.data.mode;

				if (control) {
					control.loadStart(mode, callback);
				}
			})
			.catch(() => {
				stopLoading(false);
			});
	}

	function handleKeyboardShortcuts(e: KeyboardEvent) {
		console.log('keyup', e.key, e.keyCode, e.altKey, e.ctrlKey);
		if (e.target instanceof HTMLElement && (e.target.nodeName.toLowerCase() === 'input' || e.target.nodeName.toLowerCase() === 'textarea')) {
			return;
		}
		switch (e.keyCode) {
			case 32: // Space
			case 13: // Enter
				if (!control || control.currentStatus === GameStatus.STANDBY) {
					form.requestSubmit(loadBtn);
				}
				if (control && control.currentStatus === GameStatus.ARMED) {
					form.requestSubmit(startBtn);
				}
				break;
			case 8: // Backspace
			case 46: // Delete
				if (e.ctrlKey) {
					game.clearAll();
				}
				break;
			case 86: // v
				(document.getElementById('hide-variations') as HTMLButtonElement)
					.dispatchEvent(new Event('click', {bubbles: true}));
				break;
			case 72: // h
				helpOffcanvas.toggle();
				break;
		}
	}
}