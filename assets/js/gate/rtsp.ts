import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';
import RtspWidget from './widgets/rtsp';

export default class RtspScreen extends DefaultScreen {

	private rtsp: RtspWidget;

	init(content: HTMLDivElement, removePreviousContent: () => void) {
		super.init(content, removePreviousContent);

		this.rtsp = new RtspWidget(this.content.querySelector<HTMLDivElement>('.streams'));
	}

	isSame(active: GateScreen): boolean {
		if (!(active instanceof RtspScreen)) {
			return false;
		}
		const key = this.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		const keyActive = active.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		return key === keyActive;
	}

	animateIn() {
		super.animateIn();
		this.rtsp.animateIn();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.add('timer-rtsp');
		}
	}

	animateOut() {
		super.animateOut();
		this.rtsp.animateOut();

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-rtsp');
		}
	}

}