import {startLoading, stopLoading} from '../loaders';
import {Modal, Tooltip} from 'bootstrap';
import {changeGameMode, recalcGameSkill, reimportResults, setGameGroup, syncGame} from '../api/endpoints/games';
import {ErrorResponse, FormSaveResponse} from '../includes/apiClient';
import {createGameGroup, getGameGroups} from '../api/endpoints/gameGroups';
import {planGameHighlightsTask, planGamePrecacheTask} from '../api/endpoints/tasks';
import {GameGroupData} from '../interfaces/gameInterfaces';

export default function initGamesList() {
	const checkAll = document.getElementById('game-select-check-all') as HTMLInputElement;
	const checks = document.querySelectorAll<HTMLInputElement>('.game-select-check');
	const checked = new Set<HTMLInputElement>;
	const bulkActionButtons = document.querySelectorAll<HTMLButtonElement>('#bulk-actions button');

	function toggleBulkActionButtons() {
		for (const bulkActionButton of bulkActionButtons) {
			bulkActionButton.disabled = checked.size === 0;
		}
	}

	toggleBulkActionButtons();

	function* getCheckedGameCodes(gameCode: string): Generator<string> {
		if (!gameCode || gameCode === '') {
			for (const input of checked) {
				yield input.value;
			}
		} else {
			yield gameCode;
		}
	}

	import('../datePickers').then(module => {
		// Init date pickers
		module.default();
	});

	for (const check of checks) {
		check.addEventListener('change', () => {
			if (check.checked) {
				checked.add(check);
			} else {
				checked.delete(check);
			}

			checkAll.checked = checked.size === checks.length;
			toggleBulkActionButtons();
		});
	}

	if (checkAll) {
		checkAll.addEventListener('change', () => {
			for (const check of checks) {
				check.checked = checkAll.checked;
				if (check.checked) {
					checked.add(check);
				} else {
					checked.delete(check);
				}
			}

			toggleBulkActionButtons();
		});
	}

	const groupModalElem = document.getElementById('game-group-modal') as HTMLDivElement | null;
	if (!groupModalElem) {
		console.error('No group modal');
		return;
	}
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
	});

	const reImportBtns = document.querySelectorAll('.re-import') as NodeListOf<HTMLButtonElement>;
	reImportBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		btn.addEventListener('click', () => {
			startLoading();
			const codes = getCheckedGameCodes(gameCode);
			const promises: Promise<FormSaveResponse>[] = [];
			for (const code of codes) {
				promises.push(reimportResults(code));
			}
			Promise.all(promises)
				.then(() => {
					stopLoading(true);
					window.location.reload();
				})
				.catch(() => {
					stopLoading(false);
				});
		});
	});

	const syncBtns = document.querySelectorAll('.liga-sync') as NodeListOf<HTMLButtonElement>;
	syncBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		btn.addEventListener('click', () => {
			startLoading();
			const codes = getCheckedGameCodes(gameCode);
			const promises: Promise<FormSaveResponse>[] = [];
			for (const code of codes) {
				promises.push(syncGame(code));
			}
			Promise.all(promises)
				.then(() => {
					stopLoading(true);
				})
				.catch(() => {
					stopLoading(false);
				});
		});
	});
	const recalcPointsBtns = document.querySelectorAll('.recalc-skill') as NodeListOf<HTMLButtonElement>;
	recalcPointsBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		btn.addEventListener('click', () => {
			startLoading();
			const codes = getCheckedGameCodes(gameCode);
			const promises: Promise<FormSaveResponse>[] = [];
			for (const code of codes) {
				promises.push(recalcGameSkill(code));
			}
			Promise.all(promises)
				.then(() => {
					stopLoading(true);
				})
				.catch(() => {
					stopLoading(false);
				});

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
			changeGameMode(gameCode, mode)
				.then(() => {
					stopLoading(true);
					location.reload();
				})
				.catch(() => {
					stopLoading(false);
				});
		});
	});
	const precacheBtns = document.querySelectorAll('.plan-precache') as NodeListOf<HTMLButtonElement>;
	precacheBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		btn.addEventListener('click', () => {
			startLoading();
			const codes = getCheckedGameCodes(gameCode);
			const promises: Promise<void | ErrorResponse>[] = [];
			for (const code of codes) {
				promises.push(planGamePrecacheTask(code));
			}
			Promise.all(promises)
				.then(() => {
					stopLoading(true);
				})
				.catch(() => {
					stopLoading(false);
				});

		});
	});
	const highlightsBtns = document.querySelectorAll('.plan-highlights') as NodeListOf<HTMLButtonElement>;
	highlightsBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		btn.addEventListener('click', () => {
			startLoading();
			const codes = getCheckedGameCodes(gameCode);
			const promises: Promise<void | ErrorResponse>[] = [];
			for (const code of codes) {
				promises.push(planGameHighlightsTask(code));
			}
			Promise.all(promises)
				.then(() => {
					stopLoading(true);
				})
				.catch(() => {
					stopLoading(false);
				});

		});
	});

	const groupBtns = document.querySelectorAll('.select-group') as NodeListOf<HTMLButtonElement>;
	let groupCodes: string[] = [];
	let gameGroups: GameGroupData[] = [];
	groupBtns.forEach(btn => {
		btn.addEventListener('click', () => {
			const gameCode = btn.dataset.code;
			const group = btn.dataset.group;
			console.log(btn, btn.dataset, gameCode, group, btn.dataset.groupname);
			groupCodes = [];
			const groups: { [index: string]: string } = {};
			if (gameCode) {
				groupCodes = [gameCode];
				if (group) {
					groups[group] = btn.dataset.groupname;
				}
			} else {
				for (const check of checked) {
					groupCodes.push(check.value);
					if (check.dataset.group) {
						groups[check.dataset.group] = check.dataset.groupname;
					}
				}
			}
			if (gameGroups.length > 0) {
				setSelectedGroup();
			}
			groupModal.show(btn);
			updateGameGroups();

			function updateGameGroups() {
				startLoading(true);
				getGameGroups(true)
					.then(response => {
						console.log(response);
						gameGroups = response;
						for (const groupData of response) {
							let option = groupModalSelect.querySelector<HTMLOptionElement>(`option[value="${groupData.id}"]`);
							if (!option) {
								option = document.createElement('option');
								option.value = groupData.id.toString();
								groupModalSelect.appendChild(option);
							}
							option.innerText = groupData.name;
						}

						setSelectedGroup();
						stopLoading(true, true);
						const spinner = groupModalElem.querySelector<HTMLDivElement>('.spinner-border');
						if (spinner) {
							spinner.remove();
						}
					})
					.catch(e => {
						console.error(e);
						stopLoading(false, true);
					});
			}

			function setSelectedGroup() {
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
			}
		});
	});
	groupModalElem.addEventListener('hide.bs.modal', async () => {
		startLoading();
		let groupId: number = 0;
		let groupName: string = '';
		if (groupModalSelect.value === 'new') {
			groupName = groupModalNameInput.value;
			try {
				const response = await createGameGroup(groupModalNameInput.value);
				groupId = response.id;
			} catch (e) {
				console.error(e);
				stopLoading(false);
				return;
			}
		} else if (groupModalSelect.value !== '') {
			groupId = parseInt(groupModalSelect.value);
			groupName = (groupModalSelect.querySelector(`option[value="${groupId}"]`) as HTMLOptionElement).innerText;
		}

		const promises: Promise<FormSaveResponse>[] = [];
		for (const code of groupCodes) {
			promises.push(setGameGroup(code, groupId));
		}

		Promise.all(promises)
			.then(() => {
				// Update data for all games
				for (const code of groupCodes) {
					const btns = document.querySelectorAll<HTMLButtonElement>(`.select-group[data-code="${code}"]`);
					const groupVal = groupId === 0 ? '' : groupId.toString();
					for (const btn of btns) {
						const tooltip = Tooltip.getOrCreateInstance(btn);
						if (groupId === 0) {
							btn.classList.add('btn-primary');
							btn.classList.remove('btn-success');
							btn.title = btn.dataset.label;
							btn.ariaLabel = btn.dataset.label;
							btn.dataset.bsOriginalTitle = btn.dataset.label;
							tooltip.setContent({'.tooltip-inner': btn.dataset.label});
						} else {
							btn.classList.remove('btn-primary');
							btn.classList.add('btn-success');
							btn.title = groupName;
							btn.ariaLabel = groupName;
							btn.dataset.bsOriginalTitle = groupName;
							tooltip.setContent({'.tooltip-inner': groupName});
						}
						btn.dataset.group = groupVal;
						btn.setAttribute('data-group', groupVal);
						btn.dataset.groupname = groupName;
						btn.setAttribute('data-groupname', groupName);
					}
					const check = document.querySelector<HTMLInputElement>(`.game-select-check[value="${code}"]`);
					check.dataset.group = groupVal;
					check.setAttribute('data-group', groupVal);
					check.dataset.groupname = groupName;
					check.setAttribute('data-groupname', groupName);
				}
				stopLoading(true);
			})
			.catch(e => {
				console.error(e);
				stopLoading(false);
			});
	});
}