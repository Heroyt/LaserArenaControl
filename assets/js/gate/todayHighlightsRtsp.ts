import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';

export default class TodayHighlights extends DefaultScreen {

	private interval: NodeJS.Timeout;
	private interval2: NodeJS.Timeout;
	private streamsWrapper: HTMLDivElement;
	private maxStreams: number;
	private streamUrls: string[] = [];

	isSame(active: GateScreen): boolean {
		if (!(active instanceof TodayHighlights)) {
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
			timer.classList.add('timer-today-highlights');
		}

		const wrapper = this.content.querySelector<HTMLElement>('.highlights');
		if (!wrapper) {
			return;
		}

		if (wrapper.getBoundingClientRect().height >= wrapper.scrollHeight) {
			return;
		}

		const highlights = wrapper.querySelectorAll<HTMLElement>('.highlight');
		if (highlights.length === 0) {
			return;
		}

		this.interval = setInterval(() => {
			const highlights = wrapper.querySelectorAll<HTMLElement>('.highlight');
			const height = highlights[1].getBoundingClientRect().top - highlights[0].getBoundingClientRect().top;
			this.scrollStep(wrapper, height, 2000);
		}, 8000);

		this.streamsWrapper = this.content.querySelector<HTMLDivElement>('.streams');
		this.streamUrls = JSON.parse(this.streamsWrapper.dataset.streams);
		this.maxStreams = parseInt(this.streamsWrapper.dataset.maxStreams);

		if (this.streamUrls.length > this.maxStreams) {
			let offset = 0;
			const videos = this.streamsWrapper.querySelectorAll<HTMLVideoElement>('.stream');
			this.interval2 = setInterval(() => {
				offset += this.streamUrls.length;
				for (let i = 0; i < videos.length; i++) {
					const video = videos[i];
					video.querySelector('source').src = this.streamUrls[(offset + i) % this.streamUrls.length];
					video.load();
				}
			}, 30000);
		}
	}

	animateOut() {
		super.animateOut();
		if (this.interval) {
			clearInterval(this.interval);
		}
		if (this.interval2) {
			clearInterval(this.interval2);
		}

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-today-highlights');
		}
	}

	showTimer(): boolean {
		return true;
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