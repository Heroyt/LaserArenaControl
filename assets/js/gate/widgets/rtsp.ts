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

				// Error handling
				hls.on(Hls.Events.ERROR, (event, data) => {
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
				});
			}
			video.addEventListener('canplaythrough', () => {
				video.play()
					.then(r => console.log('Playing video', this.streamUrls[(offset + i) % this.streamUrls.length], r))
					.catch(e => console.error(e));
			});
		}

		// Cycle through available cameras
		if (this.streamUrls.length > this.maxStreams) {
			this.interval = setInterval(() => {
				console.log('switching streams');
				offset += this.maxStreams;

				// Reload videos
				for (let i = 0; i < videos.length; i++) {
					const video = videos[i];
					if (video.canPlayType('application/vnd.apple.mpegurl')) {
						video.querySelector('source').src = this.streamUrls[(offset + i) % this.streamUrls.length];
						video.load();
					} else if (Hls.isSupported()) {
						// Clear HLS resources
						if (this.streams[i]) {
							this.streams[i].destroy();
						}

						// Start new stream
						this.streams[i] = new Hls({
							enableWorker: true,
							workerPath: '/dist/service-worker.js',
						});
						const hls = this.streams[i];
						hls.loadSource(this.streamUrls[(offset + i) % this.streamUrls.length]);
						hls.attachMedia(video);
					}
					video.play()
						.then(r => console.log('Playing video', this.streamUrls[(offset + i) % this.streamUrls.length], r))
						.catch(e => console.error(e));
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