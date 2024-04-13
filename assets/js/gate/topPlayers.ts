import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';

export default class TopPlayersScreen extends DefaultScreen {

	private interval: NodeJS.Timeout;
	private activeKey: number = 0;
	private players: NodeListOf<HTMLDivElement>;

	isSame(active: GateScreen): boolean {
		if (!(active instanceof TopPlayersScreen)) {
			return false;
		}
		const key = this.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		const keyActive = active.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		return key === keyActive;
	}

	animateIn() {
		super.animateIn();

		this.players = this.content.querySelectorAll<HTMLDivElement>('.top-player');
		if (this.players.length === 0) {
			return;
		}

		this.content.querySelector('.players').addEventListener('click', () => {
			this.animationStep();
		});

		this.animationStep();
		this.interval = setInterval(() => {
			this.animationStep();
		}, 10000);
	}

	animateOut() {
		super.animateOut();

		if (this.interval) {
			clearInterval(this.interval);
		}
	}

	showTimer(): boolean {
		return false;
	}

	private animationStep() {
		const prevKey = this.activeKey < 1 ? this.players.length - 1 : this.activeKey - 1;
		const prevPrevKey = this.activeKey < 2 ? this.players.length + this.activeKey - 2 : this.activeKey - 2;
		const prevPrevPrevKey = this.activeKey < 3 ? this.players.length + this.activeKey - 3 : this.activeKey - 3;
		const nextKey = (this.activeKey + 1) % this.players.length;
		const nextNextKey = (this.activeKey + 2) % this.players.length;
		const nextNextNextKey = (this.activeKey + 3) % this.players.length;

		const activePlayer = this.players[this.activeKey];
		const prevPlayer = this.players[prevKey];
		const prevPrevPlayer = this.players[prevPrevKey];
		const prevPrevPrevPlayer = this.players[prevPrevPrevKey];
		const nextPlayer = this.players[nextKey];
		const nextNextPlayer = this.players[nextNextKey];
		const nextNextNextPlayer = this.players[nextNextNextKey];

		activePlayer.classList.add('active');
		activePlayer.classList.remove('next', 'prev', 'prev-prev', 'next-next');
		prevPlayer.classList.add('prev');
		prevPlayer.classList.remove('next', 'active', 'prev-prev', 'next-next');
		prevPrevPlayer.classList.add('prev-prev');
		prevPrevPlayer.classList.remove('next', 'active', 'prev', 'next-next');
		nextPlayer.classList.add('next');
		nextPlayer.classList.remove('active', 'prev', 'prev-prev', 'next-next');
		nextNextPlayer.classList.add('next-next');
		nextNextPlayer.classList.remove('next', 'prev', 'prev-prev', 'active');
		if (this.players.length > 5) {
			setTimeout(() => {
				nextNextNextPlayer.classList.remove('next', 'prev', 'prev-prev', 'next-next', 'active');
				prevPrevPrevPlayer.classList.remove('next', 'prev', 'prev-prev', 'next-next', 'active');
			}, 500);
		}

		this.activeKey = nextKey;
	}

}