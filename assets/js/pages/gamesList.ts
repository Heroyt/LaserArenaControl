import axios, {AxiosResponse} from "axios";
import {startLoading, stopLoading} from "../loaders";
import {Modal} from "bootstrap";
import {GameGroupData} from "../interfaces/gameInterfaces";

export default function initGamesList() {
	import(/* webpackChunkName: "datePickers" */ '../datePickers').then(module => {
		// Init date pickers
		module.default()

		const groupModalElem = document.getElementById('game-group-modal') as HTMLDivElement;
		const groupModalSelect = groupModalElem.querySelector('#group-select') as HTMLSelectElement;
		const groupModalNameInputWrapper = groupModalElem.querySelector('#group-name') as HTMLDivElement;
		const groupModalNameInput = groupModalNameInputWrapper.querySelector('input') as HTMLInputElement;
		const groupModal = new Modal(groupModalElem);

		groupModalSelect.addEventListener('change', () => {
			if (groupModalSelect.value === 'new') {
				groupModalNameInputWrapper.classList.remove('d-none');
				groupModalNameInput.value = '';
			} else {
				groupModalNameInputWrapper.classList.add('d-none');
			}
		})

		const reImportBtns = document.querySelectorAll('.re-import') as NodeListOf<HTMLButtonElement>;
		reImportBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			btn.addEventListener('click', () => {
				startLoading();
				const codes = [];
				if (!gameCode || gameCode === '') {
					const checks = document.querySelectorAll('.game-select-check:checked') as NodeListOf<HTMLInputElement>;
					checks.forEach(check => {
						codes.push(check.value);
					});
				} else {
					codes.push(gameCode);
				}
				const promises: Promise<AxiosResponse>[] = [];
				codes.forEach(code => {
					promises.push(axios.post('/api/results/import/' + code, {}));
				});
				Promise.all(promises)
					.then(() => {
						stopLoading(true);
						window.location.reload();
					})
					.catch(() => {
						stopLoading(false);
					})
			});
		});

		const syncBtns = document.querySelectorAll('.liga-sync') as NodeListOf<HTMLButtonElement>;
		syncBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			btn.addEventListener('click', () => {
				startLoading();
				const codes = [];
				if (!gameCode || gameCode === '') {
					const checks = document.querySelectorAll('.game-select-check:checked') as NodeListOf<HTMLInputElement>;
					checks.forEach(check => {
						codes.push(check.value);
					});
				} else {
					codes.push(gameCode);
				}
				const promises: Promise<AxiosResponse>[] = [];
				codes.forEach(code => {
					promises.push(axios.post(`/api/games/${code}/sync`, {}));
				});
				Promise.all(promises)
					.then(() => {
						stopLoading(true);
					})
					.catch(() => {
						stopLoading(false);
					})
			});
		});
		const recalcPointsBtns = document.querySelectorAll('.recalc-skill') as NodeListOf<HTMLButtonElement>;
		recalcPointsBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			btn.addEventListener('click', () => {
				startLoading();
				const codes = [];
				if (!gameCode || gameCode === '') {
					const checks = document.querySelectorAll('.game-select-check:checked') as NodeListOf<HTMLInputElement>;
					checks.forEach(check => {
						codes.push(check.value);
					});
				} else {
					codes.push(gameCode);
				}
				const promises: Promise<AxiosResponse>[] = [];
				codes.forEach(code => {
					promises.push(axios.post('/api/game/' + code + '/recalcSkill', {}));
				});
				Promise.all(promises)
					.then(() => {
						stopLoading(true);
					})
					.catch(() => {
						stopLoading(false);
					})

			});
		});
		const soloSwitchBtns = document.querySelectorAll('.solo-switch') as NodeListOf<HTMLButtonElement>;
		soloSwitchBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			const mode = btn.dataset.mode;
			if (!gameCode || gameCode === '') {
				btn.remove();
				return;
			}
			btn.addEventListener('click', () => {
				startLoading();
				axios.post('/api/game/' + gameCode + '/changeMode', {
					mode
				})
					.then(response => {
						stopLoading(true);
						location.reload();
					})
					.catch(() => {
						stopLoading(false);
					});
			});
		});

		const groupBtns = document.querySelectorAll('.select-group') as NodeListOf<HTMLButtonElement>;
		let groupCodes: string[] = [];
		groupBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				const gameCode = btn.dataset.code;
				const group = btn.dataset.group;
				console.log(btn, btn.dataset, gameCode, group, btn.dataset.groupname);
				groupCodes = [];
				startLoading();
				const groups: { [index: string]: string } = {};
				if (gameCode) {
					groupCodes = [gameCode];
					if (group) {
						groups[group] = btn.dataset.groupname;
					}
				} else {
					const checks = document.querySelectorAll('.game-select-check:checked') as NodeListOf<HTMLInputElement>;
					checks.forEach(check => {
						groupCodes.push(check.value);
						if (check.dataset.group) {
							groups[check.dataset.group] = check.dataset.groupname;
						}
					});
				}
				axios.get('/gameGroups')
					.then((response: AxiosResponse<GameGroupData[]>) => {
						response.data.forEach(groupData => {
							let option = groupModalSelect.querySelector(`option[value="${groupData.id}"]`) as HTMLOptionElement;
							if (!option) {
								option = document.createElement('option');
								option.value = groupData.id.toString();
								groupModalSelect.appendChild(option);
							}
							option.innerText = groupData.name;
						});
						const groupKeys: string[] = Object.keys(groups);
						console.log(groups);
						if (groupKeys.length !== 1) {
							groupModalSelect.value = '';
						} else {
							groupModalSelect.value = groupKeys[0];
							if (groupModalSelect.value !== groupKeys[0]) {
								let option = document.createElement('option');
								option.value = groupKeys[0];
								option.innerText = groups[groupKeys[0]];
								groupModalSelect.appendChild(option);
							}
							groupModalSelect.value = groupKeys[0];
						}
						groupModal.show(btn);
						stopLoading(true);
					})
					.catch(e => {
						console.error(e);
						stopLoading(false);
					});
			});
		});
		groupModalElem.addEventListener('hide.bs.modal', async () => {
			startLoading();
			let groupId: number = 0;
			let groupName: string = '';
			if (groupModalSelect.value === 'new') {
				groupName = groupModalNameInput.value;
				const response = await axios.post('/gameGroups', {name: groupModalNameInput.value});
				if (response.status !== 200) {
					stopLoading(false);
					return;
				}
				groupId = response.data.id;
			} else if (groupModalSelect.value !== '') {
				groupId = parseInt(groupModalSelect.value);
				groupName = (groupModalSelect.querySelector(`option[value="${groupId}"]`) as HTMLOptionElement).innerText;
			}

			const promises: Promise<AxiosResponse>[] = [];
			groupCodes.forEach(code => {
				promises.push(axios.post(`/api/games/${code}/group`, {groupId}));
			});

			Promise.all(promises)
				.then(() => {
					// Update data for all games
					groupCodes.forEach(code => {
						const btns = document.querySelectorAll(`.select-group[data-code="${code}"]`) as NodeListOf<HTMLButtonElement>;
						const groupVal = groupId === 0 ? '' : groupId.toString();
						btns.forEach(btn => {
							if (groupId === 0) {
								btn.classList.add('btn-primary');
								btn.classList.remove('btn-success');
							} else {
								btn.classList.remove('btn-primary');
								btn.classList.add('btn-success');
							}
							btn.dataset.group = groupVal;
							btn.setAttribute('data-group', groupVal);
							btn.dataset.groupname = groupName;
							btn.setAttribute('data-groupname', groupName);
						})
						const check = document.querySelector(`.game-select-check[value="${code}"]`) as HTMLInputElement;
						check.dataset.group = groupVal;
						check.setAttribute('data-group', groupVal)
						check.dataset.groupname = groupName;
						check.setAttribute('data-groupname', groupName);
					});
					stopLoading(true);
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				})
		});
	});
}