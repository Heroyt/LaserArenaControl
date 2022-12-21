import {Collapse} from "bootstrap";
import {startLoading, stopLoading} from "../../loaders";
import axios, {AxiosResponse} from "axios";
import {GameData, GameGroupData, PlayerData} from "../../game/gameInterfaces";
import {initTooltips} from "../../functions";
import Game from "../../game/game";

export enum GroupLoadType {
	TEAMS,
	PLAYERS,
}

export default class NewGameGroup {

	game: Game;
	gameGroupsWrapper: HTMLDivElement;
	gameGroupTemplate: HTMLTemplateElement;
	gameGroupsSelect: HTMLSelectElement;

	constructor(game: Game, gameGroupsWrapper: HTMLDivElement, gameGroupTemplate: HTMLTemplateElement, gameGroupsSelect: HTMLSelectElement) {
		this.game = game;
		this.gameGroupsWrapper = gameGroupsWrapper;
		this.gameGroupTemplate = gameGroupTemplate;
		this.gameGroupsSelect = gameGroupsSelect;

		(document.querySelectorAll('.game-group') as NodeListOf<HTMLDivElement>).forEach(group => {
			this.initGroup(group);
		});
		document.getElementById('groups').addEventListener('show.bs.offcanvas', () => {
			this.updateGroups();
		});
	}

	initGroup(group: HTMLDivElement): void {
		let loadType: GroupLoadType = GroupLoadType.PLAYERS;
		const id = parseInt(group.dataset.id);
		const loadBtn = group.querySelector('.loadPlayers') as HTMLButtonElement;
		const deleteBtn = group.querySelector('.delete') as HTMLButtonElement;
		const groupName = group.querySelector('.group-name') as HTMLInputElement;

		const showGroupBtn = group.querySelector('.show-group') as HTMLButtonElement;
		const groupCollapse = new Collapse(group.querySelector(`#group-${id}-players`), {toggle: false});

		showGroupBtn.addEventListener('click', () => {
			groupCollapse.toggle();
		});

		const showTeamsBtn = group.querySelector('.show-teams') as HTMLButtonElement;
		const showPlayersBtn = group.querySelector('.show-players') as HTMLButtonElement;
		const playersCollapseDom = group.querySelector('.group-players') as HTMLUListElement;
		const teamsCollapseDom = group.querySelector('.group-teams') as HTMLUListElement;
		const playersCollapse = new Collapse(playersCollapseDom, {toggle: false});
		playersCollapse.show();
		const teamsCollapse = new Collapse(teamsCollapseDom, {toggle: false});
		teamsCollapse.hide();

		let timeout: NodeJS.Timeout = null;

		showTeamsBtn.addEventListener('click', () => {
			playersCollapse.hide();
			teamsCollapse.show();
			showTeamsBtn.classList.add('d-none');
			showPlayersBtn.classList.remove('d-none');
			loadType = GroupLoadType.TEAMS;
		});
		showPlayersBtn.addEventListener('click', () => {
			playersCollapse.show();
			teamsCollapse.hide();
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
					this.game.$group.querySelector(`option[value="${id}"]`).remove();
					group.remove();
				})
				.catch(() => {
					stopLoading(false, true);
				})
		});

		loadBtn.addEventListener('click', () => {
			const players = loadType === GroupLoadType.PLAYERS ?
				group.querySelectorAll('.group-players .group-player-check:checked') as NodeListOf<HTMLInputElement> :
				group.querySelectorAll('.group-teams .group-player-check:checked') as NodeListOf<HTMLInputElement>;

			// Prepare data for import
			const data: GameData = {
				playerCount: players.length,
				mode: {
					id: parseInt(this.game.$gameMode.value),
				},
				players: {},
				teams: {},
				music: {id: parseInt(this.game.$musicMode.value)},
				group: {
					id,
					name: groupName.value,
					active: true,
				}
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
						name: player.dataset.name,
						skill: parseInt(player.dataset.skill),
						vest,
					}
					if (loadType === GroupLoadType.TEAMS) {
						const team = player.dataset.teamColor;
						if (team) {
							const teamNum = parseInt(team);
							data.players[vest].teamNum = teamNum;
							data.players[vest].color = teamNum;
							if (!data.teams[teamNum]) {
								data.teams[teamNum] = {
									name: player.dataset.teamName,
									color: teamNum,
								};
							}
						}
					}
					vests[vest] = false;
					return;
				}

				let playerData: PlayerData = {
					name: player.dataset.name,
					skill: parseInt(player.dataset.skill),
					vest: 0,
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
								name: player.dataset.teamName,
								color: teamNum,
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
		});

		this.initGroupTeamChecks(group);
	}

	updateGroups() {
		axios.get('/gameGroups')
			.then((response: AxiosResponse<GameGroupData[]>) => {
				const vestCount = parseInt(this.gameGroupsWrapper.dataset.vests);
				response.data.forEach(groupData => {
					this.addGroup(groupData, vestCount);
				});
			});
	}

	async loadGroup(id: number) {
		const response: AxiosResponse<GameGroupData> = await axios.get(`/gameGroups/${id}`);
		const vestCount = parseInt(this.gameGroupsWrapper.dataset.vests);
		this.addGroup(response.data, vestCount);
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

		// Update teams
		if (groupData.teams) {
			const teamsWrapper = group.querySelector('.group-teams') as HTMLUListElement;
			teamsWrapper.innerHTML = '';
			Object.entries(groupData.teams).forEach(([id, team]) => {
				const teamLi = document.createElement('li');
				teamLi.classList.add('list-group-item', `bg-team-${team.system}-${team.color}`);
				teamLi.innerHTML = `<label class="cursor-pointer">` +
					`<input type="checkbox" class="form-check-input group-team-check mx-2 mt-0" data-id="${id}" data-team="${team.color}">` +
					team.name +
					`</label>`;
				teamsWrapper.appendChild(teamLi);
				let counter = 1;
				Object.entries(team.players).forEach(([name, player]) => {
					const li = document.createElement('li');
					const skill = player.skill.toString();
					li.classList.add('list-group-item', 'group-player');
					li.dataset.player = name;
					li.setAttribute('data-player', name);
					li.innerHTML = `<label class="h-100 w-100 d-flex align-items-center cursor-pointer">` +
						`<strong class="col-2 counter">${counter}.</strong>` +
						`<input type="checkbox" class="form-check-input group-player-check mx-2 mt-0" data-name="${player.name}" data-skill="${skill}" data-vest="${player.vest}" data-team-id="${id}" data-team-color="${team.color}" data-team-name="${team.name}">` +
						`<span class="flex-fill">${player.name}</span>` +
						`<span class="px-2">${player.vest}${vestIcon}</span>` +
						`<span style="min-width:3rem;" class="text-end"><span class="skill">${skill}</span><i class="fa-solid fa-star"></i></span>` +
						`</label>`;
					teamsWrapper.appendChild(li);
					counter++;
				});
			});
			this.initGroupTeamChecks(group);
		}

	}
}