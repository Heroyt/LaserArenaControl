import {Popover, Tooltip} from "bootstrap";
import axios from "axios";
import {startLoading, stopLoading} from "../loaders";
import {initTooltips} from "../functions";

export default class Player {

	/**
	 * @param vest {String}
	 * @param row {HTMLDivElement|Node}
	 * @param game {Game}
	 */
	constructor(vest, row, game) {
		this.vest = vest;
		this.row = row;
		this.game = game;

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

		const tmp = document.createElement('div');
		tmp.innerHTML = `<div class="btn-group my-3 shadow"><input type="radio" class="btn-check" name="vest[status]" id="vest-status-ok" autocomplete="off" value="ok"><label class="btn btn-outline-success" for="vest-status-ok">${messages.vestOk}</label><input type="radio" class="btn-check" name="vest[status]" id="vest-status-playable" autocomplete="off" value="playable"><label class="btn btn-outline-warning" for="vest-status-playable">${messages.vestPlayable}</label><input type="radio" class="btn-check" name="vest[status]" id="vest-status-broken" autocomplete="off" value="broken" ><label class="btn btn-outline-danger" for="vest-status-broken">${messages.vestBroken}</label></div><textarea class="form-control" name="vest[info]" id="vest-info" cols="20" rows="4">${this.$vest.dataset.info}</textarea>`;
		this.popover = new Popover(
			this.$vest,
			{
				trigger: 'click',
				title: 'Stav vesty',
				content: tmp,
				html: true,
			}
		);
		this.selectTeamTooltip = new Tooltip(
			this.row.querySelector('.team-select'),
			{
				title: messages.missingPlayerTeam,
				trigger: 'manual',
				customClass: 'tooltip-danger',
			}
		)

		const input = tmp.querySelector(`input[value="${this.$vest.dataset.status.toLowerCase()}"]`);
		if (input) {
			input.checked = true;
		}
	}

	initEvents() {
		this.$name.addEventListener('input', () => {
			this.update();
		});

		this.$teams.forEach($team => {
			const label = document.querySelector(`label[for="${$team.id}"]`);
			$team.addEventListener('change', () => {
				this.selectTeamTooltip.hide();
				this.update();
			});
			if (label) {
				label.addEventListener('click', e => {
					if ($team.checked) {
						e.preventDefault();
						$team.checked = false;
						this.update();
					}
				});
			}
		});

		this.$skills.forEach($skill => {
			$skill.addEventListener('change', () => {
				this.update();
			});
		});

		this.$clear.addEventListener('click', () => {
			this.clear();
		});

		// Hide all other popovers on show
		this.$vest.addEventListener('show.bs.popover', () => {
			this.game.players.forEach(player => {
				if (player === this) {
					return;
				}
				player.popover.hide();
			})

			// Check the correct input
			const input = this.popover._getTipElement().querySelector(`input[value="${this.$vest.dataset.status.toLowerCase()}"]`);
			if (input) {
				input.checked = true;
			}
			const textarea = this.popover._getTipElement().querySelector(`textarea`);
			if (textarea) {
				textarea.value = this.$vest.dataset.info;
			}

			this.popover._getTipElement().querySelectorAll('input, textarea').forEach(input => {
				input.addEventListener(input.tagName === 'input' ? 'change' : 'input', () => {
					startLoading(true);
					let data = {
						vest: {}
					};
					const status = this.popover._getTipElement().querySelector(`input:checked`).value;
					data.vest[this.vest] = {
						status,
						info: textarea.value,
					};
					axios.post('/settings/vests', data)
						.then(response => {
							stopLoading(response.data.success, true);
						})
						.catch(() => {
							stopLoading(false, true);
						});

					// Update status marker
					let marker = this.$vest.querySelector('.fa-solid');
					if (!marker) {
						marker = document.createElement('div');
						marker.classList.add('fa-solid', 'fa-circle-exclamation');
						marker.setAttribute('data-toggle', 'tooltip');
						this.$vest.appendChild(marker);
					}
					marker.setAttribute('title', textarea.value);

					if (status === 'ok') {
						marker.remove();
						return;
					}

					if (status === 'playable') {
						marker.classList.remove('text-danger');
						marker.classList.add('text-warning');
					} else {
						marker.classList.remove('text-warning');
						marker.classList.add('text-danger');
					}

					initTooltips(this.$vest);
				});
			})
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
		if (this.name.trim() === '' && this.$name.value.trim() !== '') {
			const e = new Event("player-activate", {
				bubbles: true,
			});
			this.$name.dispatchEvent(e);
		}
		if (this.name.trim() !== '' && this.$name.value.trim() === '') {
			const e = new Event("player-deactivate", {
				bubbles: true,
			});
			this.$name.dispatchEvent(e);
		}
		this.name = this.$name.value;
		let found = false;
		const origTeam = this.team;
		this.$teams.forEach($team => {
			if ($team.checked) {
				this.team = $team.value;
				if ($team.dataset.color) {
					this.row.style.setProperty('--shadow-color', $team.dataset.color);
					this.$vest.style.setProperty('background-color', $team.dataset.color, 'important');
					if ($team.dataset.text) {
						this.$vest.style.setProperty('color', $team.dataset.text, 'important');
					}
				}
				found = true;
			}
		});
		if (!found) {
			this.team = null;

			this.row.style.removeProperty('--shadow-color');
			this.$vest.style.removeProperty('background-color');
			this.$vest.style.removeProperty('color');
		}

		if (origTeam !== this.team) {
			// Team changed - update team objects
			let team = this.game.teams.get(origTeam);
			if (team) {
				team.update();
			}
			team = this.game.teams.get(this.team);
			if (team) {
				team.update();
			}
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

		this.row.dispatchEvent(
			new Event("update", {
				bubbles: true,
			})
		);
		this.row.dispatchEvent(
			new Event("update-player", {
				bubbles: true,
			})
		);
	}

	/**
	 *
	 * @param team {String}
	 */
	setTeam(team) {
		this.$teams.forEach($team => {
			$team.checked = $team.value === team;
		});
		this.selectTeamTooltip.hide();
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