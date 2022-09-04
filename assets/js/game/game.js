import Player from "./player";

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
		this.$teams = document.querySelectorAll('#teams .team-color-input');
		this.$shuffleTeams = document.getElementById('random-teams');
		this.$shuffleFairTeams = document.getElementById('random-fair-teams');

		this.$clearAll = document.getElementById('clear-all');

		document.querySelectorAll('.vest-row').forEach(row => {
			const vestNum = row.dataset.vest;
			this.players.set(vestNum, new Player(vestNum, row));
			row.addEventListener("update", () => {
				console.log(this);
			})
		});
		console.log(this);

		this.initEvents();
	}

	initEvents() {
		this.$clearAll.addEventListener('click', () => {
			this.players.forEach(player => {
				player.clear();
			})
		});
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

}