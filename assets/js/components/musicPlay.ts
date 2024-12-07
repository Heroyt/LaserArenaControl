import {Tooltip} from 'bootstrap';

export function initMusicPlay(wrapper: HTMLElement): void {
	const playBtn = wrapper.querySelector('.play-music') as HTMLButtonElement;
	if (!playBtn) {
		return;
	}
	const playLabel = playBtn.dataset.play;
	const stopLabel = playBtn.dataset.stop;
	let media = playBtn.dataset.file;

	if (!media) {
		playBtn.disabled = true;
		return;
	}

	let audio: HTMLAudioElement;
	const tooltip = Tooltip.getOrCreateInstance(playBtn);
	playBtn.addEventListener('click', handleClick);
	playBtn.addEventListener('reload', () => {
		pause();
		media = playBtn.dataset.file;
		if (!media) {
			playBtn.disabled = true;
			return;
		}

		audio = null;
	});

	function handleClick() {
		if (playBtn.disabled) {
			return;
		}
		playBtn.classList.add('loading');
		console.log(media);
		if (!audio) {
			audio = new Audio(media);
			audio.load();
			console.log(audio);
		}

		if (!audio.paused) {
			pause();
			return;
		}

		if (audio.readyState === HTMLMediaElement.HAVE_ENOUGH_DATA) {
			triggerPlay();
		} else {
			audio.addEventListener('canplaythrough', triggerPlay);
		}

		audio.addEventListener('ended', pause);
	}

	function pause() {
		playBtn.classList.add('btn-success');
		playBtn.classList.remove('btn-danger', 'loading', 'playing');
		if (tooltip) {
			tooltip.setContent({
				'.tooltip-inner': playLabel,
			});
		}
		// Stop
		if (audio && !audio.paused) {
			audio.pause();
		}
	}

	function triggerPlay() {
		const timeWrap = wrapper.querySelector('.time-music') as HTMLDivElement;
		if (audio.paused) {
			audio.addEventListener('timeupdate', () => {
				timeWrap.innerText = `${Math.floor(audio.currentTime / 60)}:${Math.floor(audio.currentTime % 60).toString().padStart(2, '0')}`;
			});
			playBtn.classList.remove('btn-success', 'loading');
			playBtn.classList.add('btn-danger', 'playing');
			if (tooltip) {
				tooltip.setContent({
					'.tooltip-inner': stopLabel,
				});
			}
			// Play
			audio.play();
		}
	}
}