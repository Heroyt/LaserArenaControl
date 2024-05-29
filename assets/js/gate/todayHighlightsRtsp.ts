import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';
import HighlightsWidget from './widgets/highlights';
import RtspWidget from './widgets/rtsp';

export default class TodayHighlights extends DefaultScreen {

	private highlights: HighlightsWidget;
	private rtsp: RtspWidget;

	init(content: HTMLDivElement, removePreviousContent: () => void) {
		super.init(content, removePreviousContent);

		this.highlights = new HighlightsWidget(this.content.querySelector<HTMLDivElement>('.highlights'));
		this.rtsp = new RtspWidget(this.content.querySelector<HTMLDivElement>('.streams'));
	}

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
		this.highlights.animateIn();
		this.rtsp.animateIn();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.add('timer-today-highlights');
		}
	}

	animateOut() {
		super.animateOut();
		this.highlights.animateOut();
		this.rtsp.animateOut();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-today-highlights');
		}
	}

	clear() {
		super.clear();
		delete this.highlights;
		delete this.rtsp;
	}

}