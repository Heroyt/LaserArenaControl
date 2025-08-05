import {Popover, Tooltip} from 'bootstrap';
import {startLoading, stopLoading} from '../loaders';
import Game from './game';
import {updateVests, VestData} from '../api/endpoints/settings/vests';
import {initTooltips} from '../includes/tooltips';
import {triggerNotificationError} from '../includes/notifications';

declare class CustomPopover extends Popover {
	_getTipElement(): HTMLDivElement;
}

export default class Player {

	vest: number | string;
	row: HTMLElement;
	game: Game;

	userCode: string = '';

	skill: number = 1;
	maxSkill: number = 3;
	realSkill: number = 1;
	team: string | null = null;
	name: string = '';
	vip: boolean = false;
	birthday: boolean = false;

	allowedTeams: string[] = ['0', '1', '2', '3', '4', '5'];

	$vest: HTMLElement;
	$name: HTMLInputElement;
	$userCode: HTMLInputElement;
	$findUserBtn: HTMLButtonElement;

	$teams: NodeListOf<HTMLInputElement>;
	$skills: NodeListOf<HTMLInputElement>;
	$vip: NodeListOf<HTMLInputElement>;
	$birthday: NodeListOf<HTMLInputElement>;
	$clear: HTMLButtonElement;

	popover: CustomPopover | null = null;
	selectTeamTooltip: Tooltip;
	atLeastTwoTeamsTooltip: Tooltip;
	vestId: number;

	/**
	 * @param vest {String}
	 * @param row {HTMLDivElement|Node}
	 * @param game {Game}
	 */
	constructor(vest: number | string, row: HTMLElement, game: Game) {
		this.vestId = parseInt(row.dataset.vestId ?? vest.toString());
		this.vest = vest;
		this.row = row;
		this.game = game;

		this.$vest = row.querySelector('.vest-num');
		this.$userCode = row.querySelector('.user-code');
		this.$findUserBtn = row.querySelector('.search-user');
		this.$name = row.querySelector('.player-name');
		this.$teams = row.querySelectorAll('.team-color-input');
		this.$skills = row.querySelectorAll('.player-skill-input');
		this.$vip = row.querySelectorAll('.player-vip-input');
		this.$birthday = row.querySelectorAll('.player-birthday-input');
		this.$clear = row.querySelector('.clear');

		this.initEvents();

		const tmp = document.createElement('div');
		tmp.innerHTML = `<div class="btn-group my-3 shadow"><input type="radio" class="btn-check" name="vest[status]" id="vest-status-ok" autocomplete="off" value="ok"><label class="btn btn-outline-success" for="vest-status-ok">${messages.vestOk}</label><input type="radio" class="btn-check" name="vest[status]" id="vest-status-playable" autocomplete="off" value="playable"><label class="btn btn-outline-warning" for="vest-status-playable">${messages.vestPlayable}</label><input type="radio" class="btn-check" name="vest[status]" id="vest-status-broken" autocomplete="off" value="broken" ><label class="btn btn-outline-danger" for="vest-status-broken">${messages.vestBroken}</label></div><textarea class="form-control" name="vest[info]" id="vest-info" cols="20" rows="4">${this.$vest.dataset.info}</textarea>`;
		if (!this.$vest.dataset.hideStatus) {
			this.popover = new Popover(
				this.$vest,
				{
					trigger: 'click',
					title: 'Stav vesty',
					content: tmp,
					html: true,
				},
			) as CustomPopover;
		}
		this.selectTeamTooltip = new Tooltip(
			this.row.querySelector('.team-select'),
			{
				title: messages.missingPlayerTeam,
				trigger: 'manual',
				customClass: 'tooltip-danger',
			},
		);
		this.atLeastTwoTeamsTooltip = new Tooltip(
			this.row.querySelector('.team-select'),
			{
				title: messages.atLeastTwoTeams,
				trigger: 'manual',
				customClass: 'tooltip-danger',
			},
		);

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
			this.resetUserCode();
			this.update();
			this.realSkill = this.skill;
		});

		this.$teams.forEach($team => {
			const label = document.querySelector(`label[for="${$team.id}"]`);
			$team.addEventListener('change', () => {
				this.selectTeamTooltip.hide();
				this.atLeastTwoTeamsTooltip.hide();
				this.game.atLeastTwoTeamsTooltip.hide();
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

		this.$birthday.forEach($birthday => {
			$birthday.addEventListener('change', () => {
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
				if (player === this || player.popover === null) {
					return;
				}
				player.popover.hide();
			});

			// Check the correct input
			const input = this.popover._getTipElement().querySelector<HTMLInputElement>(`input[value="${this.$vest.dataset.status.toLowerCase()}"]`);
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
					let data: VestData = {
						vest: {},
					};
					const status = (this.popover._getTipElement().querySelector(`input:checked`) as HTMLInputElement).value;
					data.vest[this.vestId] = {
						status,
						info: textarea.value,
					};
					this.$vest.dataset.status = status;
					this.$vest.setAttribute('data-status', status);
					this.$vest.dataset.info = textarea.value;
					this.$vest.setAttribute('data-info', textarea.value);
					updateVests(data)
						.then(response => {
							stopLoading(response.success, true);
						})
						.catch(async (e) => {
							await triggerNotificationError(e);
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
			});
		});

		document.addEventListener('keydown', e => {
			if (e.ctrlKey && (e.code.includes('Digit') || e.code === 'KeyS' || e.code === 'Backspace' || e.code === 'Delete')) {
				e.preventDefault();
			}
		});

		this.$name.addEventListener('keyup', e => {
			if (e.code === 'ArrowUp') { // Arrow up
				if (this.skill === this.maxSkill) {
					this.setSkill(1);
				} else {
					this.setSkill(this.skill + 1);
				}
				return;
			} else if (e.code === 'ArrowUp') { // Arrow down
				if (this.skill === 1) {
					this.setSkill(this.maxSkill);
				} else {
					this.setSkill(this.skill - 1);
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
			} else if (e.code === 'KeyS') { // s
				this.setVip(!this.vip);
			} else if (e.code === 'Backspace' || e.code === 'Delete') { // Backspace or delete
				this.clear();
			}
		});

		this.$findUserBtn.addEventListener('click', () => {
			let event = new CustomEvent<Player>(
				'user-search',
				{
					detail: this,
				},
			);
			this.row.dispatchEvent(event);
		});
	}

	clear(): void {
		this.$name.value = '';
		this.$teams.forEach($team => {
			$team.checked = false;
		});
		if (this.$skills.length > 0) {
			this.$skills.forEach($skill => {
				$skill.checked = false;
			});
			this.$skills[0].checked = true;
		}
		if (this.$vip.length > 0) {
			this.$vip.forEach($vip => {
				$vip.checked = false;
			});
			this.$vip[0].checked = true;
		}
		this.realSkill = 1;
		this.row.style.removeProperty('--shadow-color');
		this.$vest.style.removeProperty('color');
		this.$vest.style.removeProperty('background-color');
		this.resetUserCode();
		this.update();
	}

	update(): void {
		if (this.name.trim() === '' && this.$name.value.trim() !== '') {
			const e = new Event('player-activate', {
				bubbles: true,
			});
			this.$name.dispatchEvent(e);
		}
		if (this.name.trim() !== '' && this.$name.value.trim() === '') {
			const e = new Event('player-deactivate', {
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

				this.row.dataset.team = this.team;
				found = true;
			}
		});
		if (!found) {
			this.team = null;
			this.row.dataset.team = '0';

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

		if (this.$skills.length > 0) {
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
		}

		if (this.$vip.length > 0) {
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
		}

		if (this.$birthday.length > 0) {
			found = false;
			this.$birthday.forEach($birthday => {
				if ($birthday.checked) {
					this.birthday = parseInt($birthday.value) > 0;
					found = true;
				}
			});
			if (!found) {
				this.birthday = false;
				this.$birthday[0].checked = true;
			}
		}

		this.row.dispatchEvent(
			new Event('update', {
				bubbles: true,
			}),
		);
		this.row.dispatchEvent(
			new Event('update-player', {
				bubbles: true,
			}),
		);
	}

	_setTeam(team: string) {
		this.$teams.forEach($team => {
			$team.checked = $team.value === team;
		});
		this.selectTeamTooltip.hide();
		this.atLeastTwoTeamsTooltip.hide();
		this.game.atLeastTwoTeamsTooltip.hide();
	}

	setTeam(team: string): void {
		this._setTeam(team);
		this.update();
	}

	setSkill(skill: number): void {
		this._setSkill(skill);
		this.update();
		this.realSkill = this.skill;
	}

	_setSkill(skill: number) {
		this.$skills.forEach($skill => {
			$skill.checked = parseInt($skill.value) === skill;
		});
	}

	setVip(vip: boolean): void {
		this._setVip(vip);
		this.update();
	}

	_setVip(vip: boolean): void {
		const value = vip ? 1 : 0;
		this.$vip.forEach($vip => {
			$vip.checked = parseInt($vip.value) === value;
		});
	}

	setBirthday(birthday: boolean): void {
		this._setBirthday(birthday);
		this.update();
	}

	_setBirthday(birthday: boolean): void {
		const value = birthday ? 1 : 0;
		this.$birthday.forEach($birthday => {
			$birthday.checked = parseInt($birthday.value) === value;
		});
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

	resetUserCode(): void {
		this.setUserCode('');
		this.setBirthday(false);
	}

	setUserCode(code: string): void {
		this.userCode = code;
		this.$userCode.value = code;

		if (code.length > 0) {
			this.$name.classList.add('fw-semibold');
		} else {
			this.$name.classList.remove('fw-semibold');
		}
	}
}