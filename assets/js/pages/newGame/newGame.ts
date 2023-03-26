import Game from "../../game/game";
import axios, {AxiosResponse} from "axios";
import {lang} from "../../functions";
import EventServerInstance from "../../EventServer";
import {startLoading, stopLoading} from "../../loaders";
import {GameData} from "../../interfaces/gameInterfaces";
import {Modal, Offcanvas} from "bootstrap";

declare global {
	const gameData: GameData;
	const vestIcon: string;
}

enum GameStatus {
	DOWNLOAD,
	STANDBY,
	ARMED,
	PLAYING,
}

export default function initNewGamePage() {
	const form = document.getElementById('new-game-content') as HTMLFormElement;
	let currentStatus: GameStatus = GameStatus.STANDBY;

	let statusGettingInProgress = false;

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

	const downloadModalElem = document.getElementById('scoresDownloadModal') as HTMLDivElement;
	const downloadModal = new Modal(downloadModalElem);
	const retryDownloadBtn = document.getElementById('retryDownload') as HTMLButtonElement;
	const cancelDownloadBtn = document.getElementById('cancelDownload') as HTMLButtonElement;

	const gameGroupsWrapper = document.getElementById('game-groups') as HTMLDivElement;
	const gameGroupTemplate = document.getElementById('new-game-group') as HTMLTemplateElement;
	const gameGroupsSelect = document.getElementById('group-select') as HTMLSelectElement;

	const gameTablesSelect = document.getElementById('table-select') as HTMLSelectElement;

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

	stopBtn.addEventListener('click', () => {
		stopGame(new FormData(form));
	})

	updateCurrentStatus();
	// Update current status every minute
	let updateStatusInterval = setInterval(updateCurrentStatus, 60000);
	let resultsLoadRetryTimer: NodeJS.Timeout = null;

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
				startGame(data);
				break;
			case 'stop':
				stopGame(data);
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
		gameTablesSelect.value = '';
		gameGroupsSelect.value = '';
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

	import(
		/* webpackChunkName: "newGame_groups" */
		'./groups'
		)
		.then(module => {
			const groups = new module.default(game, gameGroupsWrapper, gameGroupTemplate, gameGroupsSelect);
			EventServerInstance.addEventListener('game-imported', groups.updateGroups);

			import(
				/* webpackChunkName: "newGame_tables" */
				'./tables'
				)
				.then(module => {
					new module.default(groups, gameTablesSelect);
				});
		});

	EventServerInstance.addEventListener('game-imported', loadLastGames);
	EventServerInstance.addEventListener(['game-imported', 'game-started', 'game-loaded'], updateCurrentStatus);

	retryDownloadBtn.addEventListener('click', () => {
		if (currentStatus !== GameStatus.DOWNLOAD) {
			cancelDownloadModal();
			return;
		}

		if (retryDownloadBtn.disabled) {
			return;
		}

		startLoading(true);
		axios.post('/control/retry')
			.then(() => {
				stopLoading(true, true);
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			});
	});
	cancelDownloadBtn.addEventListener('click', () => {
		if (currentStatus !== GameStatus.DOWNLOAD) {
			cancelDownloadModal();
			return;
		}

		if (cancelDownloadBtn.disabled) {
			return;
		}

		startLoading(true);
		axios.post('/control/cancel')
			.then(() => {
				stopLoading(true, true);
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			});
	});

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

	function startGame(data: FormData) {
		startLoading(true);
		getCurrentStatus()
			.then((response: AxiosResponse<{ status: string }>) => {
				stopLoading(true, true);
				if (response.data.status) {
					switch (response.data.status) {
						case 'STANDBY':
							currentStatus = GameStatus.STANDBY;
							loadStartGame(data);
							break;
						case 'ARMED':
							currentStatus = GameStatus.ARMED;
							sendStart();
							break;
						case 'PLAYING':
							currentStatus = GameStatus.PLAYING;
							// Cannot start while playing the game
							stopLoading(false, true);
							break;
						case 'DOWNLOAD':
							setCurrentStatus('DOWNLOAD');
							stopLoading(false, true);
							break;
					}
				}
				stopLoading(true, true);
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			})

		function sendStart() {
			startLoading(true);
			axios.post('/control/startSafe')
				.then((response: AxiosResponse<{ status: string }>) => {
					if (response.data.status !== 'ok') {
						setCurrentStatus(response.data.status);
						stopLoading(false);
						return;
					}
					setCurrentStatus('PLAYING');
					stopLoading(true);
				})
				.catch(error => {
					console.error(error);
					if (error.data && error.data.message && error.data.message === 'DOWNLOAD') {
						setCurrentStatus('DOWNLOAD');
					}
					stopLoading(false);
				});
		}
	}

	function stopGame(data: FormData) {
		startLoading(true);
		getCurrentStatus()
			.then((response: AxiosResponse<{ status: string }>) => {
				if (response.data.status) {
					switch (response.data.status) {
						case 'STANDBY':
							currentStatus = GameStatus.STANDBY;
							break;
						case 'ARMED':
						case 'PLAYING':
							currentStatus = response.data.status === 'ARMED' ? GameStatus.ARMED : GameStatus.PLAYING;
							axios.post('/control/stop')
								.then(() => {
									stopLoading(true, true);
									setCurrentStatus('STANDBY');
								})
								.catch(error => {
									console.error(error);
									if (error.data && error.data.message && error.data.message === 'DOWNLOAD') {
										setCurrentStatus('DOWNLOAD');
									}
									stopLoading(false, true);
								});
							break;
						case 'DOWNLOAD':
							setCurrentStatus('DOWNLOAD');
							stopLoading(false, true);
							break;
					}
				}
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			})
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

				startLoading(true);
				axios
					.post('/control/loadSafe', {
						mode,
					})
					.then((response: AxiosResponse<{ status: string }>) => {
						if (response.data.status !== 'ok') {
							setCurrentStatus(response.data.status);
							stopLoading(false, true);
							return;
						}
						setCurrentStatus('ARMED');
						if (callback) {
							callback();
						}
						stopLoading(true, true);
					})
					.catch(error => {
						stopLoading(false, true);
						console.error(error);
						if (error.data && error.data.message && error.data.message === 'DOWNLOAD') {
							setCurrentStatus('DOWNLOAD');
						}
					});
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

				startLoading(true);
				axios
					.post('/control/startSafe', {
						mode,
					})
					.then((response: AxiosResponse<{ status: string }>) => {
						if (response.data.status !== 'ok') {
							setCurrentStatus(response.data.status);
							stopLoading(false, true);
							return;
						}
						setCurrentStatus('ARMED');
						if (callback) {
							callback();
						}
						stopLoading(true, true);
					})
					.catch(error => {
						stopLoading(false, true);
						console.error(error);
						if (error.data && error.data.message && error.data.message === 'DOWNLOAD') {
							setCurrentStatus('DOWNLOAD');
						}
					});
			})
			.catch(() => {
				stopLoading(false);
			});
	}

	function updateCurrentStatus(): void {
		if (statusGettingInProgress) {
			return;
		}
		statusGettingInProgress = true;
		getCurrentStatus()
			.then((response: AxiosResponse<{ status: string }>) => {
				statusGettingInProgress = false;
				setCurrentStatus(response.data.status);
			})
			.catch(error => {
				statusGettingInProgress = false;
				console.error(error);
			})
	}

	function setCurrentStatus(status: string): void {
		loadBtn.disabled = false;
		startBtn.disabled = false;
		stopBtn.disabled = false;
		if (currentStatus === GameStatus.DOWNLOAD && status !== 'DOWNLOAD') {
			cancelDownloadModal();
		}
		switch (status) {
			case 'DOWNLOAD':
				loadBtn.disabled = true;
				startBtn.disabled = true;
				stopBtn.disabled = true;
				currentStatus = GameStatus.DOWNLOAD;
				triggerDownloadModal();
				break;
			case 'STANDBY':
				currentStatus = GameStatus.STANDBY;
				stopBtn.disabled = true;
				break;
			case 'ARMED':
				currentStatus = GameStatus.ARMED;
				break;
			case 'PLAYING':
				currentStatus = GameStatus.PLAYING;
				loadBtn.disabled = true;
				startBtn.disabled = true;
				break;
		}
		console.log(currentStatus);
	}

	function getCurrentStatus() {
		return axios.get('/control/status');
	}

	function handleKeyboardShortcuts(e: KeyboardEvent) {
		console.log('keyup', e.key, e.keyCode, e.altKey, e.ctrlKey);
		if (e.target instanceof HTMLElement && (e.target.nodeName.toLowerCase() === 'input' || e.target.nodeName.toLowerCase() === 'textarea')) {
			return;
		}
		switch (e.keyCode) {
			case 32: // Space
			case 13: // Enter
				if (currentStatus === GameStatus.STANDBY) {
					form.requestSubmit(loadBtn);
				}
				if (currentStatus === GameStatus.ARMED) {
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

	function cancelDownloadModal() {
		downloadModal.hide();

		// Reset the update status interval
		clearInterval(updateStatusInterval);
		updateStatusInterval = setInterval(updateCurrentStatus, 60000);

		retryDownloadBtn.disabled = false;
		cancelDownloadBtn.disabled = false;
		if (resultsLoadRetryTimer) {
			clearTimeout(resultsLoadRetryTimer);
		}
	}

	function triggerDownloadModal() {
		downloadModal.show();

		// Make the update status interval faster to fetch more real-time data
		clearInterval(updateStatusInterval);
		updateStatusInterval = setInterval(updateCurrentStatus, 5000);

		if (!resultsLoadRetryTimer) {
			retryDownloadBtn.disabled = true;
			cancelDownloadBtn.disabled = true;
			resultsLoadRetryTimer = setTimeout(() => {
				retryDownloadBtn.disabled = false;
				cancelDownloadBtn.disabled = false;
			}, 15000);
		}
	}
}