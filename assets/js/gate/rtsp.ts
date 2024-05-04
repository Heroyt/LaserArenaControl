import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';
import Hls from 'hls.js';

export default class RtspScreen extends DefaultScreen {

	private interval: NodeJS.Timeout;
	private streamsWrapper: HTMLDivElement;
	private maxStreams: number;
	private streamUrls: string[] = [];

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

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.add('timer-rtsp');
		}

		this.streamsWrapper = this.content.querySelector<HTMLDivElement>('.streams');
		this.streamUrls = JSON.parse(this.streamsWrapper.dataset.streams);
		this.maxStreams = parseInt(this.streamsWrapper.dataset.maxStreams);

		let offset = 0;
		const videos = this.streamsWrapper.querySelectorAll<HTMLVideoElement>('.stream');
		for (let i = 0; i < videos.length; i++) {
			const video = videos[i];
			if (!video.canPlayType('application/vnd.apple.mpegurl') && Hls.isSupported()) {
				const hls = new Hls();
				hls.loadSource(this.streamUrls[(offset + i) % this.streamUrls.length]);
				hls.attachMedia(video);
			}
		}
		if (this.streamUrls.length > this.maxStreams) {
			this.interval = setInterval(() => {
				offset += this.streamUrls.length;
				for (let i = 0; i < videos.length; i++) {
					const video = videos[i];
					if (video.canPlayType('application/vnd.apple.mpegurl')) {
						video.querySelector('source').src = this.streamUrls[(offset + i) % this.streamUrls.length];
						video.load();
					} else if (Hls.isSupported()) {
						const hls = new Hls();
						hls.loadSource(this.streamUrls[(offset + i) % this.streamUrls.length]);
						hls.attachMedia(video);
					}
				}
			}, 30000);
		}
	}

	animateOut() {
		super.animateOut();
		if (this.interval) {
			clearInterval(this.interval);
		}

		const timer = this.content.parentElement.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			timer.classList.remove('timer-rtsp');
		}
	}

}