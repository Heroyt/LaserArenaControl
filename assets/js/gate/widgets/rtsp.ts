import GateWidget from './gateWidget';
import Hls from 'hls.js';

export default class RtspWidget implements GateWidget {

	streamsWrapper: HTMLDivElement;
	private readonly maxStreams: number;
	private readonly streamUrls: string[];
	private streams: Hls[] = [];
	private interval: NodeJS.Timeout;

	constructor(streamsWrapper: HTMLDivElement) {
		this.streamsWrapper = streamsWrapper;
		this.streamUrls = JSON.parse(this.streamsWrapper.dataset.streams);
		this.maxStreams = parseInt(this.streamsWrapper.dataset.maxStreams);
	}

	animateIn(): void {
		let offset = 0;
		const videos = this.streamsWrapper.querySelectorAll<HTMLVideoElement>('.stream');
		for (let i = 0; i < videos.length; i++) {
			const video = videos[i];
			if (!video.canPlayType('application/vnd.apple.mpegurl') && Hls.isSupported()) {
				const hls = new Hls();
				hls.loadSource(this.streamUrls[(offset + i) % this.streamUrls.length]);
				hls.attachMedia(video);
			}
			video.play();
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
						this.streams[i] ??= new Hls({
							enableWorker: true,
							workerPath: '/dist/service-worker.js',
						});
						const hls = this.streams[i];
						hls.detachMedia();
						hls.loadSource(this.streamUrls[(offset + i) % this.streamUrls.length]);
						hls.attachMedia(video);
					}
					video.play();
				}
			}, 30000);
		}
	}

	animateOut(): void {
		if (this.interval) {
			clearInterval(this.interval);
		}
	}

}