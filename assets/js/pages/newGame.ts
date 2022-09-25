import Game from "../game/game";
import axios, {AxiosResponse} from "axios";
import {lang} from "../functions";
import EventServerInstance from "../EventServer";
import {startLoading, stopLoading} from "../loaders";
import {GameData} from "../game/gameInterfaces";

declare global {
	const gameData: GameData;
}

export default function initNewGamePage() {
	const form = document.getElementById('new-game-content') as HTMLFormElement;

	// Send form via ajax
	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(form);

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
	});

	loadLastGames();

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
			activePlayers.forEach(player => {
				if (player.team === null) {
					ok = false;
					player.selectTeamTooltip.show();
				}
			});
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
							loadGame(data, sendStart);
							break;
						case 'ARMED':
							sendStart();
							break;
						case 'PLAYING':
							// Cannot start while playing the game
							break;
					}
				}
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			})

		function sendStart() {
			startLoading(true);
			axios.post('/control/start')
				.then(() => {
					stopLoading(true, true);
				})
				.catch(error => {
					console.error(error);
					stopLoading(false, true);
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
							break;
						case 'ARMED':
						case 'PLAYING':
							axios.post('/control/stop')
								.then(() => {
									stopLoading(true, true);
								})
								.catch(error => {
									console.error(error);
									stopLoading(false, true);
								});
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
				getCurrentStatus()
					.then((response: AxiosResponse<{ status: string }>) => {
						if (response.data.status && response.data.status !== 'PLAYING') {
							axios
								.post('/control/load', {
									mode,
								})
								.then(() => {
									stopLoading(true, true);
									if (callback) {
										callback();
									}
								})
								.catch(error => {
									stopLoading(false, true);
									console.error(error);
								});
						}

					})
					.catch(error => {
						stopLoading(false, true);
						console.error(error);
					})
			})
			.catch(() => {
				stopLoading(false);
			});
	}

	function getCurrentStatus() {
		return axios.get('/control/status');
	}
}