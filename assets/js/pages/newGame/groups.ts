import {
	GameData,
	GameGroupData,
	PlayerData,
	PlayerGroupData,
	PlayerPayInfo,
	PriceGroup,
} from '../../interfaces/gameInterfaces';
import Game from '../../game/game';
import {createGameGroup, getGameGroup, getGameGroups, updateGameGroup} from '../../api/endpoints/gameGroups';
import {initTooltips} from '../../includes/tooltips';
import {collapseClose, collapseShow, collapseToggle, initCollapse} from '../../includes/collapse';
import {GroupLoadType, NewGameGroupInterface} from '../../interfaces/groups';
import {startLoading, stopLoading} from '../../loaders';
import {Modal} from 'bootstrap';
import {GroupDetailPlayer} from './groupDetail';
import {triggerNotificationError} from '../../includes/notifications';

export default class NewGameGroup implements NewGameGroupInterface {

	game: Game;
	gameGroupsWrapper: HTMLDivElement;
	gameGroupTemplate: HTMLTemplateElement;
	gameGroupsSelect: HTMLSelectElement;
	groupDetailModalDom: HTMLDivElement;
	private readonly groupDetailModal: Modal;
	private readonly groupDetailPlayersTable: HTMLTableElement;
	private groupDetailPaymentInfo: HTMLDivElement;
	private groupDetailPaidBtn: HTMLButtonElement;
	private groupDetailCancelBtn: HTMLButtonElement;
	private priceGroups: Map<number, PriceGroup> = new Map();
	private priceGroupsAll: Map<number, PriceGroup> = new Map();
	private readonly groupDetailSelectAll: HTMLInputElement;
	private groups: Map<number, GameGroupData> = new Map();
	private groupDetail: GameGroupData | null = null;
	private groupPlayers: Map<string, GroupDetailPlayer> = new Map();
	private selectedPlayers: Set<GroupDetailPlayer> = new Set();

	constructor(game: Game, gameGroupsWrapper: HTMLDivElement, gameGroupTemplate: HTMLTemplateElement, gameGroupsSelect: HTMLSelectElement) {
		this.game = game;
		this.gameGroupsWrapper = gameGroupsWrapper;
		this.gameGroupTemplate = gameGroupTemplate;
		this.gameGroupsSelect = gameGroupsSelect;

		this.groupDetailModalDom = document.getElementById('group-detail-modal') as HTMLDivElement;
		this.groupDetailModal = Modal.getOrCreateInstance(this.groupDetailModalDom);
		this.groupDetailPlayersTable = this.groupDetailModalDom.querySelector('#group-detail-player-table');
		this.groupDetailPaymentInfo = this.groupDetailModalDom.querySelector('.payment-info');
		this.groupDetailPaidBtn = this.groupDetailModalDom.querySelector('#paid');
		this.groupDetailCancelBtn = this.groupDetailModalDom.querySelector('#cancel-paid');

		const priceGroups: PriceGroup[] = JSON.parse(this.groupDetailPlayersTable.dataset.priceGroups);
		for (const priceGroup of priceGroups) {
			this.priceGroups.set(priceGroup.id, priceGroup);
		}
		const priceGroupsAll: PriceGroup[] = JSON.parse(this.groupDetailPlayersTable.dataset.priceGroupsAll);
		for (const priceGroup of priceGroupsAll) {
			this.priceGroupsAll.set(priceGroup.id, priceGroup);
		}

		this.groupDetailSelectAll = this.groupDetailPlayersTable.querySelector('#select-all-players') as HTMLInputElement;
		if (this.groupDetailSelectAll) {
			this.groupDetailSelectAll.addEventListener('click', () => {
				if (this.groupDetailSelectAll.checked) {
					for (const [name, player] of this.groupPlayers) {
						player.selectPlayer();
						player.togglePlayer();
					}
				} else {
					for (const [name, player] of this.groupPlayers) {
						player.deselectPlayer();
						player.togglePlayer();
					}
				}
			});
		}

		const priceGroupAll = this.groupDetailPlayersTable.querySelector('#price-group-all-players') as HTMLSelectElement;
		if (priceGroupAll) {
			priceGroupAll.addEventListener('change', () => {
				const id = parseInt(priceGroupAll.value);
				if (this.priceGroups.has(id)) {
					for (const [name, player] of this.groupPlayers) {
						player.setPriceGroup(id);
					}
				}
				priceGroupAll.value = '';
			});
		}

		this.groupDetailPaidBtn.addEventListener('click', () => {
			if (this.selectedPlayers.size === 0) {
				this.groupDetailPaidBtn.disabled = true;
				this.groupDetailCancelBtn.disabled = true;
			}
			if (this.groupDetailPaidBtn.disabled) {
				return;
			}

			for (const player of this.selectedPlayers) {
				player.payment.gamesPaid = player.payment.gamesPlayed;
				player.updatePayInfo();
				player.deselectPlayer();
			}
			this.selectedPlayers.clear();
			this.updateGroupPayment();
			this.updateCounts();
		});
		this.groupDetailCancelBtn.addEventListener('click', () => {
			if (this.selectedPlayers.size === 0) {
				this.groupDetailPaidBtn.disabled = true;
				this.groupDetailCancelBtn.disabled = true;
			}
			if (this.groupDetailCancelBtn.disabled) {
				return;
			}

			for (const player of this.selectedPlayers) {
				player.payment.gamesPaid = 0;
				player.updatePayInfo();
				player.deselectPlayer();
			}
			this.selectedPlayers.clear();
			this.updateGroupPayment();
			this.updateCounts();
		});
		this.groupDetailModalDom.addEventListener('hide.bs.modal', () => {
			this.updateGroupPayment();
		});
		const printBtn = this.groupDetailModalDom.querySelector<HTMLButtonElement>('#print-group-players');
		if (printBtn) {
			const printIframe = document.createElement('iframe');
			printIframe.style.display = 'none';
			printIframe.onload = () => {
				stopLoading();
				if (printIframe.src) {
					printIframe.contentWindow.print();
				}
			};
			document.body.appendChild(printIframe);
			printBtn.addEventListener('click', () => {
				if (!this.groupDetail || !this.groupDetail.id) {
					console.log('No group selected');
					return;
				}
				startLoading();
				printIframe.src = `/gameGroups/${this.groupDetail.id}/print`;
				console.log(printIframe.src);
			});
		}

		(document.querySelectorAll('.game-group') as NodeListOf<HTMLDivElement>).forEach(group => {
			this.initGroup(group);
		});
		document.getElementById('groups').addEventListener('show.bs.offcanvas', () => {
			this.updateGroups();
		});

		const newGroupForm: HTMLFormElement = document.getElementById('new-group-form') as HTMLFormElement;
		if (newGroupForm) {
			const newGroupName = newGroupForm.querySelector('#new-group-name') as HTMLInputElement;
			newGroupForm.addEventListener('submit', (e) => {
				e.preventDefault();

				// Validate name
				const name = newGroupName.value.trim();
				if (name.length < 1) {
					newGroupName.setCustomValidity(newGroupName.dataset.requiredError);
					return;
				}

				startLoading();
				createGameGroup(name)
					.then(() => {
						stopLoading(true);
						newGroupName.value = '';
						this.updateGroups();
					})
					.catch(e => {
						triggerNotificationError(e);
						stopLoading(false);
					});
			});
		}
	}

	initGroup(group: HTMLDivElement): void {
		console.log('Init group', group);
		let loadType: GroupLoadType = GroupLoadType.PLAYERS;
		const id = parseInt(group.dataset.id);
		const loadBtn = group.querySelector('.loadPlayers') as HTMLButtonElement;
		const deleteBtn = group.querySelector('.delete') as HTMLButtonElement;
		const groupName = group.querySelector('.group-name') as HTMLInputElement;

		const showGroupDetailBtn = group.querySelector('.show-group-detail') as HTMLButtonElement;
		if (showGroupDetailBtn) {
			if (!this.groups.has(id)) {
				showGroupDetailBtn.remove();
			} else {
				showGroupDetailBtn.addEventListener('click', () => {
					this.showGroupDetail(this.groups.get(id));
				});
			}
		}

		const showGroupBtn = group.querySelector('.show-group') as HTMLButtonElement;
		const groupCollapse = group.querySelector(`#group-${id}-players`) as HTMLDivElement;

		showGroupBtn.addEventListener('click', () => {
			collapseToggle(groupCollapse);
		});

		const showTeamsBtn = group.querySelector('.show-teams') as HTMLButtonElement;
		const showPlayersBtn = group.querySelector('.show-players') as HTMLButtonElement;
		const playersCollapseDom = group.querySelector('.players-collapse') as HTMLUListElement;
		const teamsCollapseDom = group.querySelector('.teams-collapse') as HTMLUListElement;
		collapseShow(playersCollapseDom);
		collapseClose(teamsCollapseDom);

		let timeout: NodeJS.Timeout = null;

		showTeamsBtn.addEventListener('click', () => {
			collapseClose(playersCollapseDom);
			collapseShow(teamsCollapseDom);
			showTeamsBtn.classList.add('d-none');
			showPlayersBtn.classList.remove('d-none');
			loadType = GroupLoadType.TEAMS;
		});
		showPlayersBtn.addEventListener('click', () => {
			collapseShow(playersCollapseDom);
			collapseClose(teamsCollapseDom);
			showPlayersBtn.classList.add('d-none');
			showTeamsBtn.classList.remove('d-none');
			loadType = GroupLoadType.PLAYERS;
		});

		groupName.addEventListener('input', () => {
			(this.game.$group.querySelector(`option[value="${id}"]`) as HTMLOptionElement).innerText = groupName.value;
			if (timeout) {
				clearTimeout(timeout);
			}
			timeout = setTimeout(() => {
				document.dispatchEvent(new CustomEvent('loading.small.start'));
				updateGameGroup(id, {
					name: groupName.value,
				})
					.then(() => {
						document.dispatchEvent(new CustomEvent('loading.small.stop'));
					})
					.catch(e => {
						triggerNotificationError(e);
						document.dispatchEvent(new CustomEvent('loading.small.error'));
					});
			}, 1000);
		});

		deleteBtn.addEventListener('click', () => {
			document.dispatchEvent(new CustomEvent('loading.small.start'));
			updateGameGroup(id, {active: false})
				.then(() => {
					document.dispatchEvent(new CustomEvent('loading.small.stop'));
					this.game.$group.querySelector(`option[value="${id}"]`).remove();
					group.remove();
				})
				.catch(e => {
					triggerNotificationError(e);
					document.dispatchEvent(new CustomEvent('loading.small.error'));
				});
		});

		loadBtn.addEventListener('click', () => {
			const players = loadType === GroupLoadType.PLAYERS ? group.querySelectorAll('.group-players .group-player-check:checked') as NodeListOf<HTMLInputElement> : group.querySelectorAll('.group-teams .group-player-check:checked') as NodeListOf<HTMLInputElement>;

			// Prepare data for import
			const groupData: GameGroupData = {
				id, name: groupName.value, active: true,
			};
			const data: GameData = {
				playerCount: players.length, mode: {
					id: parseInt(this.game.$gameMode.value),
				}, players: {}, teams: {}, music: {id: parseInt(this.game.$musicMode.value)}, group: groupData,
			};

			const vests: { [index: number | string]: boolean } = {};
			this.game.players.forEach(player => {
				vests[player.vest] = true;
			});

			const remainingPlayers: PlayerData[] = [];
			// Add players
			players.forEach(player => {
				const vest = isNaN(parseInt(player.dataset.vest)) ? player.dataset.vest : parseInt(player.dataset.vest);

				console.log(player.dataset.name, vest);

				if (vests[vest]) {
					data.players[vest] = {
						name: player.dataset.name, skill: parseInt(player.dataset.skill), vest,
					};
					if (loadType === GroupLoadType.TEAMS) {
						const team = player.dataset.teamColor;
						if (team) {
							const teamNum = parseInt(team);
							data.players[vest].teamNum = teamNum;
							data.players[vest].color = teamNum;
							if (!data.teams[teamNum]) {
								data.teams[teamNum] = {
									name: player.dataset.teamName, color: teamNum,
								};
							}
						}
					}
					vests[vest] = false;
					return;
				}

				let playerData: PlayerData = {
					name: player.dataset.name, skill: parseInt(player.dataset.skill), vest: 0,
				};

				if (loadType === GroupLoadType.TEAMS) {
					console.log(player.dataset.teamColor, player.dataset.team);
					const team = player.dataset.teamColor;
					if (team) {
						const teamNum = parseInt(team);
						playerData.teamNum = teamNum;
						playerData.color = teamNum;
						if (!data.teams[teamNum]) {
							data.teams[teamNum] = {
								name: player.dataset.teamName, color: teamNum,
							};
						}
					}
				}

				remainingPlayers.push(playerData);
			});

			console.log(remainingPlayers);

			Object.entries(vests).forEach(([vest, available]) => {
				if (available && remainingPlayers.length > 0) {
					const player = remainingPlayers.pop();
					player.vest = vest;
					data.players[vest] = player;
				}
			});

			this.game.import(data);

			document.dispatchEvent(new CustomEvent('game-group-loaded', {detail: groupData}));
		});

		this.initGroupTeamChecks(group);
	}

	updateGroups() {
		getGameGroups()
			.then(response => {
				const vestCount = parseInt(this.gameGroupsWrapper.dataset.vests ?? '11');
				response.forEach(groupData => {
					this.addGroup(groupData, vestCount);
				});
				initCollapse(this.gameGroupsWrapper);
			})
			.catch(e => {
				triggerNotificationError(e);
			});
	}

	async loadGroup(id: number) {
		const response = await getGameGroup(id);
		const vestCount = parseInt(this.gameGroupsWrapper.dataset.vests);
		this.addGroup(response, vestCount);
	}

	initGroupTeamChecks(group: HTMLDivElement) {
		(group.querySelectorAll('.group-team-check') as NodeListOf<HTMLInputElement>).forEach(input => {
			const teamId = input.dataset.id;

			input.addEventListener('change', () => {
				const players = document.querySelectorAll(`.group-player-check[data-team-id="${teamId}"]`) as NodeListOf<HTMLInputElement>;
				players.forEach(player => {
					player.checked = input.checked;
				});
			});
		});
	}

	addGroup(groupData: GameGroupData, vestCount: number | null = null) {
		this.groups.set(groupData.id, groupData);

		if (!vestCount) {
			vestCount = parseInt(this.gameGroupsWrapper.dataset.vests);
		}

		// Find an existing group
		let group = this.gameGroupsWrapper.querySelector(`.game-group[data-id="${groupData.id}"]`) as HTMLDivElement;
		if (!group) {
			const tmp = document.createElement('div');
			tmp.innerHTML = this.gameGroupTemplate.innerHTML
				.replaceAll('#id#', groupData.id.toString())
				.replaceAll('#name#', groupData.name);
			group = tmp.firstElementChild as HTMLDivElement;
			this.gameGroupsWrapper.appendChild(group);
			this.initGroup(group);
			initTooltips(group);
		}

		if (groupData.table && groupData.table.id && groupData.table.name) {
			group.dataset.table = groupData.table.id.toString();
			group.dataset.tablename = groupData.table.name;
			group.setAttribute('data-table', groupData.table.id.toString());
			group.setAttribute('data-tablename', groupData.table.name);
		}

		const name = group.querySelector('.group-name') as HTMLInputElement;
		name.value = groupData.name;

		// Check select option
		let option = this.game.$group.querySelector(`option[value="${groupData.id}"]`) as HTMLOptionElement;
		if (!option) {
			option = document.createElement('option');
			option.value = groupData.id.toString();
			this.game.$group.appendChild(option);
		}
		option.innerText = groupData.name;

		// Check players
		let playerCount = 0;
		const existingPlayers: { [index: string]: HTMLLIElement } = {};
		(group.querySelectorAll('.group-players .group-player') as NodeListOf<HTMLLIElement>).forEach(elem => {
			existingPlayers[elem.dataset.player] = elem;
			playerCount++;
		});
		console.log(groupData.players, existingPlayers);
		const playersWrapper = group.querySelector('.group-players') as HTMLUListElement;
		if (groupData.players) {
			Object.entries(groupData.players).forEach(([name, player]) => {
				const skill = player.skill.toString();
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
					li.innerHTML = `<label class="h-100 w-100 d-flex align-items-center cursor-pointer">` + `<strong class="col-2 counter">1.</strong>` + `<input type="checkbox" class="form-check-input group-player-check mx-2 mt-0" data-name="${player.name}" data-skill="${skill}" data-vest="${player.vest}">` + `<span class="flex-fill">${player.name}</span>` + `<span class="px-2">${player.vest}${vestIcon}</span>` + `<span style="min-width:3rem;" class="text-end"><span class="skill">${skill}</span><i class="fa-solid fa-star"></i></span></label>`;
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

		// Update teams
		if (groupData.teams) {
			const teamsWrapper = group.querySelector('.group-teams') as HTMLUListElement;
			teamsWrapper.innerHTML = '';
			Object.entries(groupData.teams).forEach(([id, team]) => {
				const teamLi = document.createElement('li');
				teamLi.classList.add('list-group-item', `text-bg-team-${team.system}-${team.color}`);
				teamLi.innerHTML = `<label class="cursor-pointer">` + `<input type="checkbox" class="form-check-input group-team-check mx-2 mt-0" data-id="${id}" data-team="${team.color}">` + team.name + `</label>`;
				teamsWrapper.appendChild(teamLi);
				let counter = 1;
				Object.entries(team.players).forEach(([name, player]) => {
					const li = document.createElement('li');
					const skill = player.skill.toString();
					li.classList.add('list-group-item', 'group-player');
					li.dataset.player = name;
					li.setAttribute('data-player', name);
					li.innerHTML = `<label class="h-100 w-100 d-flex align-items-center cursor-pointer">` + `<strong class="col-2 counter">${counter}.</strong>` + `<input type="checkbox" class="form-check-input group-player-check mx-2 mt-0" data-name="${player.name}" data-skill="${skill}" data-vest="${player.vest}" data-team-id="${id}" data-team-color="${team.color}" data-team-name="${team.name}">` + `<span class="flex-fill">${player.name}</span>` + `<span class="px-2">${player.vest}${vestIcon}</span>` + `<span style="min-width:3rem;" class="text-end"><span class="skill">${skill}</span><i class="fa-solid fa-star"></i></span>` + `</label>`;
					teamsWrapper.appendChild(li);
					counter++;
				});
			});
			this.initGroupTeamChecks(group);
		}

	}

	showGroupDetail(group: GameGroupData) {
		console.log('Show group detail', group);

		this.groupDetail = group;
		this.groupPlayers.clear();
		this.selectedPlayers.clear();
		this.groupDetailSelectAll.checked = false;

		for (const nameWrapper of this.groupDetailModalDom.querySelectorAll<HTMLElement>('.group-detail-name')) {
			nameWrapper.innerText = group.name;
		}

		if (group.players) {
			this.groupDetailPlayers(
				group.players,
				'meta' in group && group.meta.payment ? group.meta.payment : {},
			);
		}

		this.updateCounts();
		this.groupDetailModal.show();
	}

	private groupDetailPlayers(
		players: { [index: string]: PlayerGroupData },
		payment: { [index: string]: PlayerPayInfo },
	): void {
		// Sort players by name
		const playersSorted = Object.values(players)
			.sort((a, b) => a.name.localeCompare(b.name));

		console.log('players', playersSorted);
		console.log('payment', payment);

		// Create table
		const tableBody = this.groupDetailPlayersTable.querySelector('tbody');
		// Clear table body
		tableBody.innerHTML = '';
		// Add players
		for (const player of playersSorted) {
			const playerObj = new GroupDetailPlayer(
				player,
				this.groupDetailPlayersTable,
				this.priceGroups,
				this.priceGroupsAll,
				(player) => {
					this.selectedPlayers.add(player);
					this.updateCounts();
				},
				(player) => {
					this.selectedPlayers.delete(player);
					this.updateCounts();
				},
				(player) => {
					this.updateCounts();
				},
			);
			if ((player.asciiName in payment)) {
				playerObj.setPayment(payment[player.asciiName]);
			}
			this.groupPlayers.set(player.asciiName, playerObj);
		}
	}

	private updateCounts() {
		console.log(this.selectedPlayers);
		this.groupDetailPaidBtn.disabled = this.selectedPlayers.size === 0;
		this.groupDetailCancelBtn.disabled = this.selectedPlayers.size === 0;

		this.groupDetailSelectAll.checked = this.selectedPlayers.size === this.groupPlayers.size;

		if (this.priceGroups.size > 0) {
			const sums: { [index: number]: { sum: number, played: number } } = {};
			for (const player of this.selectedPlayers) {
				const id = player.payment.priceGroupId;
				if (!(id in sums)) {
					sums[id] = {
						sum: 0,
						played: 0,
					};
				}
				sums[id].sum += player.payment.gamesPlayed - player.payment.gamesPaid;
				sums[id].played += player.payment.gamesPlayed;
			}

			let html = `<ul class="m-0">`;
			let sumMoney = 0;
			for (const [id, priceGroup] of this.priceGroupsAll) {
				if (!(id in sums)) {
					continue;
				}
				const {sum, played} = sums[id];
				sumMoney += sum * priceGroup.price;
				html += `<li><strong>${priceGroup.name}:</strong><ul class="m-0">`;
				if (played !== sum) {
					html += `<li><strong>${this.groupDetailPaymentInfo.dataset.playedLabel}:</strong> ${played} (${(priceGroup.price * played).toLocaleString(undefined, {
						style: 'currency',
						currency: 'CZK',
					})})</li>`;
				}
				html += `<li><strong>${this.groupDetailPaymentInfo.dataset.unpaidLabel}:</strong> ${sum} (${(priceGroup.price * sum).toLocaleString(undefined, {
						style: 'currency',
						currency: 'CZK',
					})})</li>` +
					`</ul></li>`;
			}
			html += `<li class="mt-3">${this.groupDetailPaymentInfo.dataset.topayLabel}:</strong> ${sumMoney.toLocaleString(undefined, {
				style: 'currency',
				currency: 'CZK',
			})}</li>`;
			html += `</ul>`;
			this.groupDetailPaymentInfo.innerHTML = html;
		} else {
			let sum = 0;
			let sumPlayed = 0;
			for (const player of this.selectedPlayers) {
				sum += player.payment.gamesPlayed - player.payment.gamesPaid;
				sumPlayed += player.payment.gamesPlayed;
			}

			let html = `<ul class="m-0">`;
			if (sumPlayed !== sum) {
				html += `<li><strong>${this.groupDetailPaymentInfo.dataset.playedLabel}:</strong> ${sumPlayed}</li>`;
			}
			html += `<li><strong>${this.groupDetailPaymentInfo.dataset.unpaidLabel}:</strong> ${sum}</li></ul>`;
			this.groupDetailPaymentInfo.innerHTML = html;
		}
	}

	private updateGroupPayment() {
		if (!this.groupDetail) {
			return;
		}

		const group = this.groupDetail;

		// Update data
		if (!('meta' in group) || group.meta instanceof Array) {
			group.meta = {
				payment: {},
			};
		}
		if (!('payment' in group.meta)) {
			// @ts-ignore
			group.meta.payment = {};
		}

		for (const [name, player] of this.groupPlayers) {
			group.meta.payment[name] = player.payment;
		}

		console.log(group);

		startLoading(true);
		updateGameGroup(group.id, group)
			.then(() => {
				stopLoading(true, true);
				this.groups.set(group.id, group);
			})
			.catch(e => {
				triggerNotificationError(e);
				stopLoading(false, true);
			});
	}
}