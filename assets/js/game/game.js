import Player from "./player";
import Team from "./team";
import {shuffle} from "../functions";
import {Tooltip} from "bootstrap";

export default class Game {

	constructor() {

		/**
		 * @type {Map<String, Player>}
		 */
		this.players = new Map;
		this.teams = new Map;

		/**
		 * @type {HTMLSelectElement}
		 */
		this.$gameMode = document.getElementById('game-mode-select');
		/**
		 * @type {HTMLSelectElement}
		 */
		this.$musicMode = document.getElementById('music-select');
		/**
		 * @type {NodeListOf<HTMLInputElement>}
		 */
		this.$teams = document.querySelectorAll('#teams-random .team-color-input');
		this.$shuffleTeams = document.getElementById('random-teams');
		this.$shuffleFairTeams = document.getElementById('random-fair-teams');

		this.$soloHide = document.querySelectorAll('.solo-hide');

		this.$clearAll = document.getElementById('clear-all');

		this.teamShuffleTooltip = new Tooltip(
			document.getElementById('team-random-select'),
			{
				title: messages.missingShuffleTeam,
				trigger: 'manual',
				customClass: 'tooltip-danger',
			}
		)
		this.noPlayersTooltip = new Tooltip(
			document.querySelector('.vest-row'),
			{
				title: messages.noPlayersActive,
				trigger: 'manual',
				customClass: 'tooltip-danger',
			}
		)

		document.querySelectorAll('.vest-row').forEach(row => {
			const vestNum = row.dataset.vest;
			this.players.set(vestNum, new Player(vestNum, row, this));

			row.addEventListener('player-activate', () => {
				this.noPlayersTooltip.hide();
			})
		});
		document.querySelectorAll('.team-row').forEach(row => {
			const key = row.dataset.key;
			this.teams.set(key, new Team(key, row, this));
		});
		console.log(this);

		this.initEvents();
	}

	initEvents() {
		this.$clearAll.addEventListener('click', () => {
			this.players.forEach(player => {
				player.clear();
			});
			this.teams.forEach(team => {
				team.clear();
			});

			const e = new Event('clear-all');
			document.dispatchEvent(e);
		});

		this.$gameMode.addEventListener('change', () => {
			const type = this.getModeType();
			console.log(type, this.$soloHide);

			this.$soloHide.forEach(elem => {
				if (type === 'SOLO') {
					elem.classList.add('d-none');
				} else {
					elem.classList.remove('d-none');
				}
			});

			this.$gameMode.dispatchEvent(
				new Event("update", {
					bubbles: true,
				})
			);
		});

		this.$shuffleTeams.addEventListener('click', () => {
			this.shuffleTeams();
		})

		this.$shuffleFairTeams.addEventListener('click', () => {
			this.shuffleFairTeams();
		})

		this.$teams.forEach($team => {
			$team.addEventListener('change', () => {
				this.teamShuffleTooltip.hide();
			});
		})
	}

	/**
	 * @return 'SOLO'|'TEAM'
	 */
	getModeType() {
		return this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`).dataset.type;
	}

	getSelectedTeams() {
		const teams = [];
		this.$teams.forEach($team => {
			if ($team.checked) {
				teams.push($team.value);
			}
		});
		return teams;
	}

	/**
	 * @returns {Player[]}
	 */
	getActivePlayers() {
		const players = [];
		this.players.forEach(player => {
			if (player.isActive()) {
				players.push(player);
			}
		});
		return players;
	}

	getActiveTeams() {
		if (this.getModeType() === 'SOLO') {
			return [];
		}
		const teams = [];
		this.teams.forEach(team => {
			if (team.playerCount > 0) {
				teams.push(team);
			}
		});
		return teams;
	}

	shuffleTeams() {
		// Clear all teams
		this.players.forEach(player => {
			player.setTeam('');
		});

		const players = shuffle(this.getActivePlayers());

		if (players.length < 2) {
			this.noPlayersTooltip.show();
			return;
		}

		const teams = shuffle(this.getSelectedTeams());
		const teamCount = teams.length;

		if (teamCount < 2) {
			console.log('Cannot shuffle fairly without at least 2 teams selected.');
			this.teamShuffleTooltip.show();
			return;
		}

		let i = 0;
		players.forEach(player => {
			player.setTeam(teams[i % teamCount]);
			i++;
		});
	}

	shuffleFairTeams() {
		// Clear all teams
		this.players.forEach(player => {
			player.setTeam('');
		});

		const players = this.getActivePlayers();

		if (players.length < 2) {
			this.noPlayersTooltip.show();
			return;
		}

		console.log('active players', players);

		const teams = shuffle(this.getSelectedTeams());
		const teamCount = teams.length;

		if (teamCount < 2) {
			console.log('Cannot shuffle fairly without at least 2 teams selected.');
			this.teamShuffleTooltip.show();
			return;
		}

		// Create group objects for each team created
		let groups = [];
		teams.forEach(team => {
			groups.push({
				team,
				skill: 0,
				players: [],
			});
		});

		// Sort players into 3 groups by their skill
		const skills = {
			1: [],
			2: [],
			3: [],
		};
		players.forEach(player => {
			skills[player.skill].push(player);
		});

		if (skills[1].length === players.length || skills[2].length === players.length || skills[3].length === players.length) {
			// All players are in one skill group
			// It makes sense to shuffle the players normally
			this.shuffleTeams();
			return;
		}

		// Shuffle skill sets - players
		skills[1] = shuffle(skills[1]);
		skills[2] = shuffle(skills[2]);
		skills[3] = shuffle(skills[3]);

		const skillSum = skills[1].length + (2 * skills[2].length) + (3 * skills[3].length);
		const teamAverage = skillSum / teamCount;
		const sortedPlayers = skills[3].concat(skills[2]).concat(skills[1]);
		console.log('sorted players', sortedPlayers);

		// Fill the teams with players
		let i = 0;
		sortedPlayers.forEach(player => {
			groups[i % teamCount].players.push(player);
			groups[i % teamCount].skill += player.skill;
			i++;
		});

		console.log('groups', groups);
		console.log('sum, average', skillSum, teamAverage);

		// Iterate to mix the teams to be as fair as possible
		const maxIterations = 500;
		const maxIterationsWithoutImprovement = 40;
		let iterationsWithoutImprovement = 0;
		let it;
		for (it = 0; it < maxIterations && iterationsWithoutImprovement < maxIterationsWithoutImprovement; it++) {
			// Randomly select 2 groups
			// Shuffle to prevent using Math.random and then looping to check if the indexes aren't the same
			groups = shuffle(groups);
			// Get the first 2 groups from the shuffled array
			const group1 = groups[0];
			const group2 = groups[1];

			// Score the 2 groups - the lower, the better
			// Score is based from the absolute difference from the calculated average
			const score = Math.abs(group1.skill - teamAverage) + Math.abs(group2.skill - teamAverage);

			// Randomly choose 2 players in groups
			const player1Key = Math.floor(Math.random() * group1.players.length);
			const player2Key = Math.floor(Math.random() * group2.players.length);
			const player1 = group1.players[player1Key];
			const player2 = group2.players[player2Key];

			// The skills are the same, it doesn't matter if they swap.
			if (player1.skill === player2.skill) {
				// Swap with 50% probability.
				if (Math.random() <= 0.5) {
					group1.players[player1Key] = player2;
					group2.players[player2Key] = player1;
				}
				iterationsWithoutImprovement++;
				continue;
			}

			// Calculate new skill levels after swapping
			const skill1 = group1.skill - player1.skill + player2.skill;
			const skill2 = group2.skill - player2.skill + player1.skill;

			// Calculate the new score
			const newScore = Math.abs(skill1 - teamAverage) + Math.abs(skill2 - teamAverage);

			// Check improvement
			if (newScore < score) {
				// The score improved after swapping.

				// Swap players
				group1.players[player1Key] = player2;
				group2.players[player2Key] = player1;

				// Update skills
				group1.skill = skill1;
				group2.skill = skill2;

				// Reset iterations without improvements counter
				iterationsWithoutImprovement = 0;
				continue;
			}

			// The score did not improve => do not swap
			iterationsWithoutImprovement++;
		}

		console.log('iterations', it);
		console.log('iterations without improvements', iterationsWithoutImprovement);
		console.log('groups', groups);

		// Assign teams
		groups.forEach(group => {
			group.players.forEach(player => {
				player.setTeam(group.team);
			});
		});
	}

	/**
	 * @param data {GameData}
	 */
	import(data) {
		const skills = Object.values(data.players).map(playerData => {
			return playerData.skill;
		});
		const maxSkill = Math.max(3, ...skills);
		const minSkill = Math.min(0, ...skills);
		const skillStep = (minSkill + maxSkill) / 3;
		Object.values(data.players).forEach(playerData => {
			const player = this.players.get(playerData.vest.toString());
			if (!player) {
				return;
			}
			player.$name.value = playerData.name;
			if (playerData.color) {
				player.setTeam(playerData.color.toString());
			}
			player.setSkill(Math.ceil((minSkill + playerData.skill) / skillStep));
			player.update();
		});
		Object.values(data.teams).forEach(teamData => {
			const team = this.teams.get(teamData.color.toString());
			if (!team) {
				return;
			}
			team.$name.value = teamData.name;
			team.update();
		});

		this.$gameMode.value = data.mode.id.toString();
		const e = new Event('change');
		this.$gameMode.dispatchEvent(e);
	}

	export() {
		const activePlayers = this.getActivePlayers();
		const activeTeams = this.getActiveTeams();

		/**
		 * @type {GameData}
		 */
		const data = {
			playerCount: activePlayers.length,
			mode: {
				id: parseInt(this.$gameMode.value),
				name: this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`).innerText.trim(),
				type: this.getModeType(),
			},
			players: {},
			teams: {},
		};

		activePlayers.forEach(player => {
			data.players[player.vest] = {
				name: player.name,
				vest: parseInt(player.vest),
				teamNum: parseInt(player.team),
				color: parseInt(player.team),
				skill: player.skill,
			}
		});

		activeTeams.forEach(team => {
			data.teams[team.key] = {
				name: team.name,
				color: parseInt(team.key),
				playerCount: team.playerCount,
			}
		});

		return data;
	}
}