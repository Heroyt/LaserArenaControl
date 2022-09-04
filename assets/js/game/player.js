export default class Player {

	/**
	 *
	 * @param vest {String}
	 * @param row {HTMLDivElement|Node}
	 */
	constructor(vest, row) {
		this.vest = vest;
		this.row = row;

		this.skill = 1;
		this.team = null;
		this.name = '';

		this.$vest = row.querySelector('.vest-num');
		this.$name = row.querySelector('.player-name');
		/**
		 * @type {RadioNodeList}
		 */
		this.$teams = row.querySelectorAll('.team-color-input');
		/**
		 * @type {RadioNodeList}
		 */
		this.$skills = row.querySelectorAll('.player-skill-input');
		this.$clear = row.querySelector('.clear');

		this.initEvents();
	}

	initEvents() {
		this.$name.addEventListener('input', () => {
			this.update();
		});

		this.$teams.forEach($team => {
			$team.addEventListener('change', () => {
				this.update();
			});
		});

		this.$skills.forEach($skill => {
			$skill.addEventListener('change', () => {
				this.update();
			});
		});

		this.$clear.addEventListener('click', () => {
			this.clear();
		});
	}

	clear() {
		this.$name.value = '';
		this.$teams.forEach($team => {
			$team.checked = false;
		});
		this.$skills.forEach($skill => {
			$skill.checked = false;
		});
		this.$skills[0].checked = true;
		this.row.style.removeProperty('--shadow-color');
		this.$vest.style.removeProperty('color');
		this.$vest.style.removeProperty('background-color');
		this.update();
	}

	update() {
		this.name = this.$name.value;
		let found = false;
		this.$teams.forEach($team => {
			if ($team.checked) {
				console.log($team, $team.value);
				this.team = $team.value;
				if ($team.dataset.color) {
					this.row.style.setProperty('--shadow-color', $team.dataset.color);
					console.log(this.$vest.style, $team.dataset.color + ' !important');
					this.$vest.style.setProperty('background-color', $team.dataset.color, 'important');
					if ($team.dataset.text) {
						console.log($team.dataset.text);
						this.$vest.style.setProperty('color', $team.dataset.text, 'important');
					}
				}
				found = true;
			}
		});
		if (!found) {
			this.team = null;
		}
		found = false;
		this.$teams.forEach($team => {
			if ($team.checked) {
				this.team = $team.value;
				found = true;
			}
		});
		if (!found) {
			this.team = null;
		}
		found = false;
		this.$skills.forEach($skill => {
			if ($skill.checked) {
				this.skill = parseInt($skill.value);
				found = true;
			}
		});
		if (!found) {
			this.skill = 1;
			this.$skills[0].checked = true;
		}
		const e = new Event("update", {
			bubbles: true,
		});
		this.$name.dispatchEvent(e);
		this.row.dispatchEvent(e);
	}

	/**
	 *
	 * @param team {String}
	 */
	setTeam(team) {
		this.$teams.forEach($team => {
			$team.checked = $team.value === team;
		});
		this.update();
	}

	/**
	 *
	 * @param skill {Number}
	 */
	setSkill(skill) {
		this.$skills.forEach($skill => {
			$skill.checked = parseInt($skill.value) === skill;
		});
		this.update();
	}

	/**
	 * @returns {boolean}
	 */
	isActive() {
		return this.name.trim() !== '';
	}
}