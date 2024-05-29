import GateWidget from './gateWidget';
import Hls, {ErrorData, Events} from 'hls.js';

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

				// Error handling
				hls.on(Hls.Events.ERROR, (event, data) => this.handleHlsError(hls, event, data));
			}
			video.oncanplaythrough = () => {
				video.play()
					.then(r => console.log('Playing video', this.streamUrls[(offset + i) % this.streamUrls.length], r))
					.catch(e => console.error(e));
			};
		}

		// Cycle through available cameras
		if (this.streamUrls.length > this.maxStreams) {
			this.interval = setInterval(() => {
				console.log('switching streams');
				offset += this.maxStreams;

				// Reload videos
				for (let i = 0; i < videos.length; i++) {
					const video = videos[i];
					this.refreshStream(i, offset, video);
				}
			}, 30000);
		}
	}

	handleHlsError(hls: Hls, event: Events.ERROR, data: ErrorData) {
		console.error(event, data);

		if (data.fatal) {
			switch (data.type) {
				case Hls.ErrorTypes.MEDIA_ERROR:
					console.log('fatal media error encountered, try to recover');
					hls.recoverMediaError();
					break;
				case Hls.ErrorTypes.NETWORK_ERROR:
					console.error('fatal network error encountered', data);
					// All retries and media options have been exhausted.
					// Immediately trying to restart loading could cause loop loading.
					// Consider modifying loading policies to best fit your asset and network
					// conditions (manifestLoadPolicy, playlistLoadPolicy, fragLoadPolicy).
					break;
				default:
					// cannot recover
					hls.destroy();
					break;
			}
		}
	}

	private refreshStream(i: number, offset: number, video: HTMLVideoElement) {
		video.pause();
		const media = this.streamUrls[(offset + i) % this.streamUrls.length];
		if (video.canPlayType('application/vnd.apple.mpegurl')) {
			video.querySelector('source').src = media;
			video.load();
			video.oncanplaythrough = () => {
				video.play()
					.then(() => console.log('Playing video', media))
					.catch(e => console.error(e));
			};
			return;
		}

		if (Hls.isSupported()) {
			// Clear HLS resources
			if (this.streams[i]) {
				this.streams[i].removeAllListeners();
				this.streams[i].detachMedia();
				this.streams[i].stopLoad();
				this.streams[i].destroy();
			}

			// Start new stream
			this.streams[i] = new Hls({
				enableWorker: true,
				workerPath: '/dist/service-worker.js',
			});
			const hls = this.streams[i];

			// Error handling
			hls.on(Hls.Events.ERROR, (event, data) => this.handleHlsError(hls, event, data));

			console.log('Loading media', media);
			hls.loadSource(media);
			hls.attachMedia(video);
		}

		video.play()
			.then(() => console.log('Playing video', media))
			.catch(e => console.error(e));
	}

	animateOut(): void {
		if (this.interval) {
			clearInterval(this.interval);
		}
	}

}