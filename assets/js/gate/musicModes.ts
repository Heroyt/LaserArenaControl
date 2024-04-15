import DefaultScreen from './defaultScreen';

export default class MusicModes extends DefaultScreen {

	private interval: NodeJS.Timeout;

	animateIn() {
		super.animateIn();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.add('timer-music-modes');
		}

		const wrapper = this.content.querySelector<HTMLElement>('.music-modes-wrapper');
		if (!wrapper) {
			return;
		}

		const height = wrapper.getBoundingClientRect().height;
		if (height >= wrapper.scrollHeight) {
			return;
		}

		let bottom = false;
		this.interval = setInterval(() => {
			this.scroll(wrapper, bottom ? 0 : wrapper.scrollHeight - height, 10000);
			bottom = !bottom;
		}, 15000);
	}

	animateOut() {
		super.animateOut();
		if (this.interval) {
			clearInterval(this.interval);
		}

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-music-modes');
		}
	}

	showTimer(): boolean {
		return true;
	}

	private scroll(wrapper: HTMLElement, scrollTo: number, duration: number, callback: null | (() => void) = null) {
		let start: number, previousTimeStamp: number;

		const startScroll = wrapper.scrollTop;
		const scrollBy = scrollTo - startScroll;

		function scroll(timeStamp: number) {
			if (start === undefined) {
				start = timeStamp;
			}
			const elapsed = timeStamp - start;

			if (previousTimeStamp !== timeStamp) {
				wrapper.scrollTop = startScroll + (scrollBy * (elapsed / duration));
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