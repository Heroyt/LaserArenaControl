import {Popover, Tooltip} from "bootstrap";
import axios from "axios";
import {startLoading, stopLoading} from "../loaders";
import {initTooltips} from "../functions";
import Game from "./game";

declare module "bootstrap" {
	class Popover {
		_getTipElement(): HTMLElement;
	}
}

export default class Player {

	vest: number | string;
	row: HTMLElement;
	game: Game;

	skill: number = 1;
	maxSkill: number = 3;
	realSkill: number = 1;
	team: string | null = null;
	name: string = '';
	vip: boolean = false;

	allowedTeams: string[] = ['0', '1', '2', '3', '4', '5'];

	$vest: HTMLElement;
	$name: HTMLInputElement;

	$teams: NodeListOf<HTMLInputElement>;
	$skills: NodeListOf<HTMLInputElement>;
	$vip: NodeListOf<HTMLInputElement>;
	$clear: HTMLButtonElement;

	popover: Popover;
	selectTeamTooltip: Tooltip;

	/**
	 * @param vest {String}
	 * @param row {HTMLDivElement|Node}
	 * @param game {Game}
	 */
	constructor(vest: number | string, row: HTMLElement, game: Game) {
		this.vest = vest;
		this.row = row;
		this.game = game;

		this.$vest = row.querySelector('.vest-num');
		this.$name = row.querySelector('.player-name');
		this.$teams = row.querySelectorAll('.team-color-input');
		this.$skills = row.querySelectorAll('.player-skill-input');
		this.$vip = row.querySelectorAll('.player-vip-input');
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

		const input = tmp.querySelector(`input[value="${this.$vest.dataset.status.toLowerCase()}"]`) as HTMLInputElement;
		if (input) {
			input.checked = true;
		}
	}

	updateAllowedTeams(teams: string[]) {
		this.allowedTeams = teams;
		this.$teams.forEach(input => {
			if (!teams.includes(input.value)) {
				input.checked = false;
				input.classList.add('hide');
			} else {
				input.classList.remove('hide');
				if (teams.length === 1 && this.isActive()) {
					input.checked = true;
				}
			}
			input.dispatchEvent(new Event('change', {bubbles: true}));
		});
	}

	initEvents(): void {
		this.$name.addEventListener('input', () => {
			this.update();
			this.realSkill = this.skill;
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
				this.realSkill = this.skill;
			});
		});

		this.$vip.forEach($vip => {
			$vip.addEventListener('change', () => {
				this.update();
			});
		});

		this.$clear.addEventListener('click', () => {
			this.clear();
		});

		// Hide all other popovers on show
		this.$vest.addEventListener('show.bs.popover', () => {
			const closeTrigger = document.createElement('div');
			closeTrigger.classList.add('position-fixed', 'vw-100', 'vh-100', 'cursor-pointer');
			closeTrigger.style.zIndex = '1069';
			closeTrigger.style.backgroundColor = 'rgba(0,0,0,0.3)';
			closeTrigger.style.top = '0';
			closeTrigger.style.left = '0';
			document.body.appendChild(closeTrigger);

			closeTrigger.addEventListener('click', () => {
				closeTrigger.remove();
				this.popover.hide();
			});

			this.game.players.forEach(player => {
				if (player === this) {
					return;
				}
				player.popover.hide();
			})

			// Check the correct input
			const input = this.popover._getTipElement().querySelector(`input[value="${this.$vest.dataset.status.toLowerCase()}"]`) as HTMLInputElement;
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
					let data: { vest: { [index: string | number]: { status: string, info: string } } } = {
						vest: {}
					};
					const status = (this.popover._getTipElement().querySelector(`input:checked`) as HTMLInputElement).value;
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

		this.$name.addEventListener('keyup', e => {
			if (e.keyCode === 38) { // Arrow up
				if (this.skill === this.maxSkill) {
					this.setSkill(1);
				} else {
					this.setSkill(this.skill + 1)
				}
				return;
			} else if (e.keyCode === 40) { // Arrow down
				if (this.skill === 1) {
					this.setSkill(this.maxSkill);
				} else {
					this.setSkill(this.skill - 1)
				}
				return;
			}
			if (!e.ctrlKey) {
				return;
			}

			if (e.code.includes('Digit')) {
				const index = parseInt(e.code.replace('Digit', '')) - 1;
				if (this.$teams[index]) {
					this.setTeam(this.$teams[index].value);
				}
			} else if (e.keyCode === 86) { // v
				this.setVip(!this.vip);
			} else if (e.keyCode === 8 || e.keyCode === 46) { // Backspace or delete
				this.clear();
			}
		});
	}

	clear(): void {
		this.$name.value = '';
		this.$teams.forEach($team => {
			$team.checked = false;
		});
		this.$skills.forEach($skill => {
			$skill.checked = false;
		});
		this.$skills[0].checked = true;
		this.$vip.forEach($vip => {
			$vip.checked = false;
		});
		this.$vip[0].checked = true;
		this.realSkill = 1;
		this.row.style.removeProperty('--shadow-color');
		this.$vest.style.removeProperty('color');
		this.$vest.style.removeProperty('background-color');
		this.update();
	}

	update(): void {
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

		if (this.allowedTeams.length === 1) {
			this._setTeam(this.isActive() ? this.allowedTeams[0] : '');
		}

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

		found = false;
		this.$vip.forEach($vip => {
			if ($vip.checked) {
				this.vip = parseInt($vip.value) > 0;
				found = true;
			}
		});
		if (!found) {
			this.vip = false;
			this.$vip[0].checked = true;
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

	_setTeam(team: string) {
		this.$teams.forEach($team => {
			$team.checked = $team.value === team;
		});
		this.selectTeamTooltip.hide();
	}

	setTeam(team: string): void {
		this._setTeam(team);
		this.update();
	}

	setSkill(skill: number): void {
		this.$skills.forEach($skill => {
			$skill.checked = parseInt($skill.value) === skill;
		});
		this.update();
		this.realSkill = this.skill;
	}

	setVip(vip: boolean): void {
		const value = vip ? 1 : 0;
		this.$vip.forEach($vip => {
			$vip.checked = parseInt($vip.value) === value;
		});
		this.update();
	}

	isActive(): boolean {
		return this.name.trim() !== '';
	}

	setMaxSkill(max: 3 | 6): void {
		const label: HTMLLabelElement = this.row.querySelector('.maxSkillSwitch');

		this.maxSkill = max;
		if (max === 3) {
			label.setAttribute('for', `player-skill-${this.vest}-1`);
		} else {
			label.setAttribute('for', `player-skill-${this.vest}-4`);
		}
	}
}