import CustomLoadMode from "../customLoadMode";

import {lang} from '../../includes/frameworkFunctions';

export default class Barvicky extends CustomLoadMode {

	randomHiddenTeamsSwitch: HTMLButtonElement;
	randomHiddenTeamsInput: HTMLInputElement;
	randomHiddenTeams: boolean = false;

	init() {
		super.init();
		const soloHide = document.querySelectorAll('.solo-hide');
		soloHide.forEach(elem => {
			elem.classList.remove('d-none');
		})

		const modeVariations = document.getElementById('game-mode-variations') as HTMLDivElement;
		this.randomHiddenTeamsInput = document.createElement('input');
		this.randomHiddenTeamsInput.type = "hidden";
		this.randomHiddenTeamsInput.name = 'hiddenTeams';
		this.randomHiddenTeamsInput.value = this.randomHiddenTeams ? '1' : '0';
		modeVariations.appendChild(this.randomHiddenTeamsInput);
		this.randomHiddenTeamsSwitch = document.createElement('button');
		this.randomHiddenTeamsSwitch.type = 'button';
		this.randomHiddenTeamsSwitch.classList.add('btn', this.randomHiddenTeams ? 'btn-primary' : 'btn-outline-primary');
		this.randomHiddenTeamsSwitch.innerText = 'Skryté náhodné týmy';
		lang('Skryté náhodné týmy', null, 1, 'modes.settings')
			.then(translated => {
				this.randomHiddenTeamsSwitch.innerText = translated;
			})
		modeVariations.appendChild(this.randomHiddenTeamsSwitch);

		this.randomHiddenTeamsSwitch.addEventListener('click', () => {
			this.randomHiddenTeamsSwitch.classList.toggle('btn-primary');
			this.randomHiddenTeamsSwitch.classList.toggle('btn-outline-primary');
			this.randomHiddenTeams = !this.randomHiddenTeams;
			this.randomHiddenTeamsInput.value = this.randomHiddenTeams ? '1' : '0';
			soloHide.forEach(elem => {
				if (this.randomHiddenTeams) {
					elem.classList.add('d-none');
				} else {
					elem.classList.remove('d-none');
				}
			})
		});

	}

	cancel() {
		super.cancel();
		this.randomHiddenTeamsInput.remove();
		this.randomHiddenTeamsSwitch.remove();
	}

}