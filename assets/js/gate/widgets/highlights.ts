import GateWidget from './gateWidget';

export default class HighlightsWidget implements GateWidget {

	wrapper: HTMLDivElement;
	private interval: NodeJS.Timeout;

	constructor(wrapper: HTMLDivElement) {
		this.wrapper = wrapper;
	}

	animateIn() {
		if (this.wrapper.getBoundingClientRect().height >= this.wrapper.scrollHeight) {
			return;
		}

		const highlights = this.wrapper.querySelectorAll<HTMLElement>('.highlight');
		if (highlights.length === 0) {
			return;
		}

		this.interval = setInterval(() => {
			const highlights = this.wrapper.querySelectorAll<HTMLElement>('.highlight');
			const height = highlights[1].getBoundingClientRect().top - highlights[0].getBoundingClientRect().top;
			this.scrollStep(this.wrapper, height, 2000);
		}, 8000);
	}

	animateOut() {
		if (this.interval) {
			clearInterval(this.interval);
		}
	}

	private scrollStep(wrapper: HTMLElement, height: number, duration: number): void {
		const first = wrapper.querySelector<HTMLElement>('.highlight');
		const copy = first.cloneNode(true);
		wrapper.appendChild(copy);
		this.scroll(wrapper, height, duration, () => {
			first.remove();
			wrapper.scrollTo({top: 0, behavior: 'instant'});
		});
	}

	private scroll(wrapper: HTMLElement, scrollBy: number, duration: number, callback: null | (() => void) = null) {
		const easing = (t: number) => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
		let start: number, previousTimeStamp: number;

		function scroll(timeStamp: number) {
			if (start === undefined) {
				start = timeStamp;
			}
			const elapsed = timeStamp - start;

			if (previousTimeStamp !== timeStamp) {
				wrapper.scrollTop = scrollBy * easing(elapsed / duration);
			}

			if (elapsed > duration) {
				callback && callback();
				return; // Stop animation after duration
			}
			previousTimeStamp = timeStamp;
			window.requestAnimationFrame(scroll);
		}

		window.requestAnimationFrame(scroll);
	}

}