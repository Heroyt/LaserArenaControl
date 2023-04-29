import Player from '../../tournament/Player';
import Control from "../newGame/control";
import {isFeatureEnabled} from "../../featureConfig";
import {startLoading, stopLoading} from "../../loaders";
import axios, {AxiosResponse} from "axios";
import EventServerInstance from "../../EventServer"

declare global {
	const vests: {
		id: number,
		vestNum: string,
	}[]
}

export default function initTournamentPlay() {
	const form = document.getElementById('tournament-play-form') as HTMLFormElement;
	initContent(form);

	EventServerInstance.addEventListener('game-imported', updateContent);

	function updateContent() {
		axios.get(form.dataset.results)
			.then((response: AxiosResponse<{ status: string, view?: string }>) => {
				if (response.data.view) {
					form.innerHTML = response.data.view;
					initContent(form);
				}
			});
	}
}

function initContent(form: HTMLFormElement) {
	const progressTeams = document.getElementById('progressTeams') as HTMLButtonElement;
	progressTeams.addEventListener('click', () => {
		startLoading(true);
		axios.post(progressTeams.dataset.action, {})
			.then((response: AxiosResponse<{ progressed: number }>) => {
				stopLoading(true, true);
				alert(progressTeams.dataset.alert + ' ' + response.data.progressed);
			})
			.catch(e => {
				console.error(e);
				stopLoading(false, true);
			});
	});

	const results = document.getElementById('results') as HTMLDivElement | undefined;
	if (results) {
		const resetBtn = document.getElementById('reset-game') as HTMLButtonElement;
		resetBtn.addEventListener('click', () => {
			if (!confirm(resetBtn.dataset.confirm)) {
				return;
			}
			startLoading();
			axios.post(resetBtn.dataset.action, {})
				.then(() => {
					window.location.reload();
				})
				.catch(e => {
					stopLoading(false);
					console.error(e);
				});
		});

		const bonusInputs = results.querySelectorAll('.team-bonus') as NodeListOf<HTMLInputElement>;
		const updateBonusBtn = results.querySelector('#updateBonus') as HTMLButtonElement;
		const updateBonusAction = updateBonusBtn.dataset.action;

		bonusInputs.forEach(input => {
			input.addEventListener('input', () => {
				const value = parseInt(input.value);
				if (isNaN(value)) {
					input.classList.remove('text-danger', 'text-success');
				} else if (value > 0) {
					input.classList.remove('text-danger');
					input.classList.add('text-success');
				} else {
					input.classList.add('text-danger');
					input.classList.remove('text-success');
				}
			});
		});

		updateBonusBtn.addEventListener('click', () => {
			const bonus: { [index: number]: number } = {};
			bonusInputs.forEach(input => {
				if (input.value === '') {
					input.value = '0';
				}
				const value = parseInt(input.value);
				const id = parseInt(input.dataset.team);
				bonus[id] = value;
			});

			startLoading();
			axios.post(updateBonusAction, {bonus})
				.then(() => {
					window.location.reload();
				})
				.catch(e => {
					stopLoading(false);
					console.error(e);
				});
		});

		return;
	}

	const players: Map<number, Player> = new Map();

	const loadBtn = document.getElementById('load') as HTMLButtonElement;
	const startBtn = document.getElementById('start') as HTMLButtonElement;
	const stopBtn = document.getElementById('stop') as HTMLButtonElement;

	let control: Control | null = null;
	if (isFeatureEnabled('control')) {
		control = new Control(loadBtn, startBtn, stopBtn);
	}

	// Send form via ajax
	form.onsubmit = e => {
		e.preventDefault();

		const data = new FormData(form);

		console.log(e.submitter);

		if (!data.get('action')) {
			data.set('action', (e.submitter as HTMLButtonElement).value)
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
	};

	const playersDom = document.querySelectorAll('.player') as NodeListOf<HTMLDivElement>;
	playersDom.forEach(dom => {
		const id = parseInt(dom.dataset.id);
		const player = new Player(id, dom);
		player.vestSelect.addEventListener('change', updateAvailableVests);
		players.set(id, player);
	});

	updateAvailableVests();

	function updateAvailableVests() {
		const selectedVests: string[] = [];
		players.forEach(player => {
			const value = player.vestSelect.value;
			if (value !== '') {
				selectedVests.push(value);
			}
		});

		const availableVests: string[] = [];
		vests.forEach(vest => {
			if (!selectedVests.includes(vest.vestNum)) {
				availableVests.push(vest.vestNum);
			}
		});

		players.forEach(player => {
			let vests = [...availableVests];
			if (player.vestSelect.value !== '') {
				vests.push(player.vestSelect.value);
			}
			player.setVestOptions(vests);
		});
	}

	function loadGame(data: FormData, callback: null | (() => void) = null): void {
		startLoading();
		axios.post(form.getAttribute('action'), data)
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
}