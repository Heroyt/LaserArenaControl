import Game from "../game/game";
import axios, {AxiosResponse} from "axios";
import {initTooltips, lang} from "../functions";
import EventServerInstance from "../EventServer";
import {startLoading, stopLoading} from "../loaders";
import {GameData, GameGroupData, PlayerData, TableData} from "../game/gameInterfaces";
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

	gameTablesSelect.addEventListener('change', async () => {
		await selectTable(gameTablesSelect.value);
	});

	stopBtn.addEventListener('click', () => {
		stopGame(new FormData(form));
	})

	updateCurrentStatus();
	// Update current status every minute
	let updateStatusInterval = setInterval(updateCurrentStatus, 60000);

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

	EventServerInstance.addEventListener('game-imported', loadLastGames);
	EventServerInstance.addEventListener(['game-imported', 'game-started', 'game-loaded'], updateCurrentStatus);
	EventServerInstance.addEventListener('game-imported', updateGroups);

	document.getElementById('groups').addEventListener('show.bs.offcanvas', updateGroups);
	document.getElementById('tables').addEventListener('show.bs.offcanvas', updateTables);

	retryDownloadBtn.addEventListener('click', () => {
		if (currentStatus !== GameStatus.DOWNLOAD) {
			cancelDownloadModal();
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

	(document.querySelectorAll('.game-group') as NodeListOf<HTMLDivElement>).forEach(initGroup);
	(document.querySelectorAll('.game-table') as NodeListOf<HTMLDivElement>).forEach(initTable);

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
								const teamString = game.mode.type === 'TEAM' ? values[1].data.replace('%d', teamCount) + ', ' : '';
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
	}

	function triggerDownloadModal() {
		downloadModal.show();

		// Make the update status interval faster to fetch more real-time data
		clearInterval(updateStatusInterval);
		updateStatusInterval = setInterval(updateCurrentStatus, 5000);
	}

	function initGroup(group: HTMLDivElement): void {
		const id = parseInt(group.dataset.id);
		const loadBtn = group.querySelector('.loadPlayers') as HTMLButtonElement;
		const deleteBtn = group.querySelector('.delete') as HTMLButtonElement;
		const groupName = group.querySelector('.group-name') as HTMLInputElement;

		let timeout: NodeJS.Timeout = null;

		groupName.addEventListener('input', () => {
			(game.$group.querySelector(`option[value="${id}"]`) as HTMLOptionElement).innerText = groupName.value;
			if (timeout) {
				clearTimeout(timeout);
			}
			timeout = setTimeout(() => {
				startLoading(true);
				axios
					.post('/gameGroups/' + id.toString(), {
						name: groupName.value,
					})
					.then(() => {
						stopLoading(true, true);
					})
					.catch(() => {
						stopLoading(false, true);
					})
			}, 1000);
		});

		deleteBtn.addEventListener('click', () => {
			startLoading(true);
			axios
				.post('/gameGroups/' + id.toString(), {
					active: '0',
				})
				.then(() => {
					stopLoading(true, true);
					game.$group.querySelector(`option[value="${id}"]`).remove();
					group.remove();
				})
				.catch(() => {
					stopLoading(false, true);
				})
		});

		loadBtn.addEventListener('click', () => {
			const players = group.querySelectorAll('.group-player-check:checked') as NodeListOf<HTMLInputElement>;

			// Prepare data for import
			const data: GameData = {
				playerCount: players.length,
				mode: {
					id: parseInt(game.$gameMode.value),
				},
				players: {},
				teams: {},
				music: {id: parseInt(game.$musicMode.value)},
				group: {
					id,
					name: groupName.value,
					active: true,
				}
			};

			const vests: { [index: number | string]: boolean } = {};
			game.players.forEach(player => {
				vests[player.vest] = true;
			});

			const remainingPlayers: PlayerData[] = [];
			// Add players
			players.forEach(player => {
				const vest = isNaN(parseInt(player.dataset.vest)) ? player.dataset.vest : parseInt(player.dataset.vest);

				console.log(player.dataset.name, vest);

				if (vests[vest]) {
					data.players[vest] = {
						name: player.dataset.name,
						skill: parseInt(player.dataset.skill),
						vest,
					}
					vests[vest] = false;
					return;
				}

				remainingPlayers.push(
					{
						name: player.dataset.name,
						skill: parseInt(player.dataset.skill),
						vest: 0,
					}
				);
			});

			console.log(remainingPlayers);

			Object.entries(vests).forEach(([vest, available]) => {
				if (available && remainingPlayers.length > 0) {
					const player = remainingPlayers.pop();
					player.vest = vest;
					data.players[vest] = player;
				}
			});

			game.import(data);
		});
	}

	function updateGroups() {
		axios.get('/gameGroups')
			.then((response: AxiosResponse<GameGroupData[]>) => {
				const vestCount = parseInt(gameGroupsWrapper.dataset.vests);
				response.data.forEach(groupData => {
					addGroup(groupData, vestCount);
				});
			});
	}

	async function loadGroup(id: number) {
		const response: AxiosResponse<GameGroupData> = await axios.get(`/gameGroups/${id}`);
		const vestCount = parseInt(gameGroupsWrapper.dataset.vests);
		addGroup(response.data, vestCount);
	}

	function addGroup(groupData: GameGroupData, vestCount: number | null = null) {
		if (!vestCount) {
			vestCount = parseInt(gameGroupsWrapper.dataset.vests);
		}

		// Find an existing group
		let group = gameGroupsWrapper.querySelector(`.game-group[data-id="${groupData.id}"]`) as HTMLDivElement;
		if (!group) {
			const tmp = document.createElement('div');
			tmp.innerHTML = gameGroupTemplate.innerHTML
				.replaceAll('#id#', groupData.id.toString())
				.replaceAll('#name#', groupData.name);
			group = tmp.firstElementChild as HTMLDivElement;
			gameGroupsWrapper.appendChild(group);
			initGroup(group);
			initTooltips(group);
		}

		const name = group.querySelector('.group-name') as HTMLInputElement;
		name.value = groupData.name;

		// Check select option
		let option = game.$group.querySelector(`option[value="${groupData.id}"]`) as HTMLOptionElement;
		if (!option) {
			option = document.createElement('option');
			option.value = groupData.id.toString();
			game.$group.appendChild(option);
		}
		option.innerText = groupData.name;

		// Check players
		let playerCount = 0;
		const existingPlayers: { [index: string]: HTMLLIElement } = {};
		(group.querySelectorAll('.group-player') as NodeListOf<HTMLLIElement>).forEach(elem => {
			existingPlayers[elem.dataset.player] = elem;
			playerCount++;
		});
		const playersWrapper = group.querySelector('.group-players') as HTMLUListElement;
		if (groupData.players) {
			Object.entries(groupData.players).forEach(([name, player]) => {
				const skill = (player.avgSkill ? player.avgSkill : player.skill).toString();
				if (existingPlayers[name]) {
					const input = existingPlayers[name].querySelector('.group-player-check') as HTMLInputElement;
					input.dataset.skill = skill;
					(existingPlayers[name].querySelector('.skill') as HTMLSpanElement).innerText = skill;
					delete existingPlayers[name];
				} else {
					const li = document.createElement('li');
					li.classList.add('list-group-item', 'group-player');
					li.dataset.player = name;
					li.setAttribute('data-player', name);
					li.innerHTML = `<label class="h-100 w-100 d-flex align-items-center cursor-pointer">` +
						`<strong class="col-2 counter">1.</strong>` +
						`<input type="checkbox" class="form-check-input group-player-check mx-2 mt-0" data-name="${player.name}" data-skill="${skill}" data-vest="${player.vest}">` +
						`<span class="flex-fill">${player.name}</span>` +
						`<span class="px-2">${player.vest}${vestIcon}</span>` +
						`<span style="min-width:3rem;" class="text-end"><span class="skill">${skill}</span><i class="fa-solid fa-star"></i></span></label>`;
					playersWrapper.appendChild(li);
					playerCount++;
				}
			});
		}

		// Clear removed players
		Object.keys(existingPlayers).forEach(key => {
			existingPlayers[key].remove();
			playerCount--;
		});

		// Update counters
		let counter = 1;
		(playersWrapper.querySelectorAll('.counter') as NodeListOf<HTMLSpanElement>).forEach(elem => {
			elem.innerText = counter.toString() + '.';
			counter++;
		});

		// Update checked
		let checked = playersWrapper.querySelectorAll(`.group-player-check:checked`).length;
		if (checked < vestCount && checked < playerCount) {
			(playersWrapper.querySelectorAll(`.group-player-check:not(:checked)`) as NodeListOf<HTMLInputElement>).forEach(input => {
				if (checked < vestCount) {
					input.checked = true;
					checked++;
				}
			});
		}
	}

	function initTable(table: HTMLDivElement): void {
		const id = parseInt(table.dataset.id);

		const cleanBtn = table.querySelector('.clean') as HTMLButtonElement;

		table.addEventListener('click', async (e: MouseEvent) => {
			// Prevent trigger if clicked on the cleanBtn
			const target = e.target as HTMLElement;
			if (target === cleanBtn || target.parentElement === cleanBtn) {
				return;
			}
			await selectTable(id);
		});

		cleanBtn.addEventListener('click', () => {
			startLoading();
			axios.post(`/tables/${id}/clean`, {})
				.then(() => {
					updateTable(id);
					stopLoading();
				})
				.catch(() => {
					stopLoading(false);
				})
		});
	}

	async function selectTable(id: number | string) {
		console.log('Selecting table', id);
		const activeTable = document.querySelector('.game-table.active') as HTMLDivElement | null;
		if (activeTable) {
			activeTable.classList.remove('active', 'bg-success', 'text-bg-success');
			if (activeTable.dataset.group) {
				activeTable.classList.add('bg-purple-600', 'text-bg-purple-600');
			} else {
				activeTable.classList.add('bg-purple-400', 'text-bg-purple-400');
			}
		}
		const table = document.querySelector(`.game-table[data-id="${id}"]`) as HTMLDivElement | null;
		if (!table) {
			return;
		}
		console.log(table, table.dataset.group ?? "");
		table.classList.remove('bg-purple-400', 'bg-purple-600', 'text-bg-purple-400', 'text-bg-purple-600');
		table.classList.add('active', 'bg-success', 'text-bg-success');

		if (table.dataset.group) {
			const groupId = parseInt(table.dataset.group);
			let groupDom = gameGroupsWrapper.querySelector(`.game-group[data-id="${groupId}"]`) as HTMLDivElement;
			if (!groupDom) {
				// Load group if it doesn't exist (for example if it's disabled)
				startLoading(true);
				await loadGroup(groupId);
				groupDom = gameGroupsWrapper.querySelector(`.game-group[data-id="${groupId}"]`) as HTMLDivElement;
				stopLoading(true, true);
			}
			// Dispatch a click event on the loadPlayers btn
			groupDom.querySelector('.loadPlayers').dispatchEvent(new Event('click', {bubbles: true}));
		} else {
			gameGroupsSelect.value = "";
		}

		gameTablesSelect.value = id.toString();
		gameTablesSelect.dispatchEvent(new Event('update', {bubbles: true}));
	}

	function updateTableData(table: TableData) {
		const tableDom = document.querySelector(`.game-table[data-id="${table.id}"]`) as HTMLDivElement | null;
		if (!tableDom) {
			return;
		}
		const cleanBtn = tableDom.querySelector('.clean') as HTMLButtonElement;

		if (table.group) {
			tableDom.dataset.group = table.group.id.toString();
			if (tableDom.classList.contains('bg-purple-400')) {
				tableDom.classList.remove('bg-purple-400', 'text-bg-purple-400');
				tableDom.classList.add('bg-purple-600', 'text-bg-purple-600');
			}
			cleanBtn.classList.remove('d-none');
		} else {
			tableDom.dataset.group = "";
			if (tableDom.classList.contains('bg-purple-600')) {
				tableDom.classList.remove('bg-purple-600', 'text-bg-purple-600');
				tableDom.classList.add('bg-purple-400', 'text-bg-purple-400');
			}
			cleanBtn.classList.add('d-none');
		}
	}

	function updateTable(id: number): void {
		axios.get(`/tables/${id}`)
			.then((response: AxiosResponse<TableData>) => {
				const table = response.data;
				updateTableData(table);
			});
	}

	function updateTables(): void {
		axios.get('/tables')
			.then((response: AxiosResponse<{ tables: TableData[] }>) => {
				response.data.tables.forEach(updateTableData);
			})
	}
}