import CustomLoadMode from '../customLoadMode';

export default class Gladiator extends CustomLoadMode {
	init() {
		super.init();
		const vipInputs = document.querySelectorAll('.player-vip');
		vipInputs.forEach(elem => {
			elem.classList.add('d-none');
		});
	}

	cancel() {
		super.cancel();
		const vipInputs = document.querySelectorAll('.player-vip');
		vipInputs.forEach(elem => {
			elem.classList.remove('d-none');
		});
	}

}