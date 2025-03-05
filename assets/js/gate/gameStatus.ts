import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';

export default class GameStatus extends DefaultScreen {


	isSame(active: GateScreen): boolean {
		if (!(active instanceof GameStatus)) {
			return false;
		}
		const key = this.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		const keyActive = active.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		return key === keyActive;
	}

	animateIn() {
		super.animateIn();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.add('timer-game-status');
		}
	}

	animateOut() {
		super.animateOut();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-game-status');
		}
	}
}