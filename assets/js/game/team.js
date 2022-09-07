export default class Team {

	/**
	 * @param key {String}
	 * @param row {HTMLDivElement}
	 * @param game {Game}
	 */
	constructor(key, row, game) {
		this.key = key;
		this.row = row;
		this.game = game;

		this.playerCount = 0;
		this.name = '';

		/**
		 * @type {HTMLDivElement}
		 */
		this.$playerCount = row.querySelector('.player-count');
		this.$name = row.querySelector('.team-name');
	}

	clear() {
		this.$name.value = this.$name.dataset.default;
		this.update();
	}

	initEvents() {
		this.$name.addEventListener('input', () => {
			this.name = this.$name.value;
		});
	}

	update() {
		this.recountPlayers();

		if (this.playerCount === 0) {
			this.row.classList.add('d-none');
		} else {
			this.row.classList.remove('d-none');
		}

		this.$playerCount.innerText = this.playerCount.toString();

		this.name = this.$name.value;


		this.row.dispatchEvent(
			new Event("update", {
				bubbles: true,
			})
		);
		this.row.dispatchEvent(
			new Event("update-team", {
				bubbles: true,
			})
		);
	}

	recountPlayers() {
		this.playerCount = 0;
		this.game.players.forEach(player => {
			if (player.team === this.key) {
				this.playerCount++;
			}
		});
		return this.playerCount;
	}

}