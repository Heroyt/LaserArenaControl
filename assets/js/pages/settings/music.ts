import {startLoading, stopLoading} from "../../loaders";
import axios, {AxiosResponse} from "axios";
import {initTooltips, lang} from "../../functions";
import Sortable from "sortablejs";
import {Tooltip} from "bootstrap";
import {Music} from "../../interfaces/gameInterfaces";


export default function initMusicSettings() {
	const uploadInput = document.getElementById('media') as HTMLInputElement;
	const uploadForm = document.getElementById('upload-form') as HTMLFormElement;

	const musicForm = document.getElementById('music-settings-form') as HTMLFormElement;
	const musicWrapper = document.getElementById('musicInputsWrapper') as HTMLDivElement;

	const notices = document.getElementById('notices') as HTMLDivElement;
	const musicTemplate = document.getElementById('musicInputTemplate').innerHTML;

	if (uploadForm && uploadInput) {
		uploadForm.addEventListener('submit', e => {
			e.preventDefault();

			startLoading();
			upload(0, uploadInput.files);
		});
	}

	(document.querySelectorAll('.music-input') as NodeListOf<HTMLDivElement>).forEach(initMusic);

	const sortableMusic = new Sortable(musicWrapper, {
		handle: '.counter',
		draggable: '.music-input',
		ghostClass: 'cursor-grabbing',
		onSort: recountMusic,
	});

	function upload(index: number, files: FileList) {
		if (index >= files.length) {
			stopLoading();
			return;
		}

		const data = new FormData();
		const file = files[index];
		console.log(index, file);
		data.append('action', 'upload');
		data.append('media[]', file);

		axios.post(uploadForm.getAttribute('action'), data, {
			headers: {
				"Content-Type": "multipart/form-data",
			}
		})
			.then(handleResponse)
			.catch(handleResponse)

		function handleResponse(response: AxiosResponse<{ errors: string[], notices: { type: string, content: string }[], music: Music[] }>) {
			if (response.data) {
				if (response.data.errors) {
					response.data.errors.forEach(error => {
						addAlert('danger', error);
					});
				}
				if (response.data.notices) {
					response.data.notices.forEach(notice => {
						addAlert(notice.type, notice.content);
					});
				}
				if (response.data.music) {
					response.data.music.forEach(info => {
						addMusic(info);
					});
				}
			}
			recountMusic();
			upload(++index, files);
		}
	}

	function addAlert(type: string, content: string) {
		const div = document.createElement('div');
		div.classList.add('alert', 'alert-' + type)
		div.innerHTML = content;
		notices.appendChild(div);
	}

	function addMusic(info: Music) {
		const tmp = document.createElement('div');
		console.log(musicTemplate);
		const html = musicTemplate
			.replaceAll('#id#', info.id.toString())
			.replace('#name#', info.name)
			.replace('#counter#', '0')
			.replace('#file#', info.media);
		console.log(html);
		tmp.innerHTML = html;
		console.log(info, tmp, tmp.firstElementChild);
		musicWrapper.appendChild(tmp.firstElementChild);
		initMusic(tmp.firstElementChild as HTMLDivElement);
		initTooltips(tmp.firstElementChild as HTMLElement);
		recountMusic();
	}

	function recountMusic() {
		let counter = 1;
		(document.querySelectorAll('.music-input') as NodeListOf<HTMLDivElement>).forEach(elem => {
			(elem.querySelector('.counter') as HTMLElement).innerText = counter.toString();
			(elem.querySelector('.order-input') as HTMLInputElement).value = counter.toString();
			counter++;
		});
	}

	function initMusic(elem: HTMLDivElement) {
		const id = elem.dataset.id;
		const deleteBtn = elem.querySelector('.remove') as HTMLButtonElement;
		if (deleteBtn) {
			deleteBtn.addEventListener('click', () => {
				startLoading();
				axios.delete('/settings/music/' + id)
					.then(() => {
						elem.remove();
						stopLoading();
						recountMusic();
					})
					.catch(() => {
						stopLoading(false);
					})
			});
		}
		const playBtn = elem.querySelector('.play-music') as HTMLButtonElement;
		if (playBtn) {
			const media = playBtn.dataset.file;
			let audio: HTMLAudioElement;
			const tooltip = Tooltip.getInstance(playBtn);
			playBtn.addEventListener('click', () => {
				playBtn.innerHTML = `<div class="spinner-grow spinner-grow-sm" role="status"><span class="visually-hidden">Loading...</span></div>`;
				if (!audio) {
					audio = new Audio(media);
				}

				if (!audio.paused) {
					playBtn.classList.add('btn-success');
					playBtn.classList.remove('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-play"></i>`;
					lang('Přehrát', null, 1, 'actions')
						.then((response: AxiosResponse<string>) => {
							tooltip.setContent({
								'.tooltip-inner': response.data,
							});
						});
					// Stop
					audio.pause();
					return;
				}

				if (audio.readyState === HTMLMediaElement.HAVE_ENOUGH_DATA) {
					triggerPlay();
				} else {
					audio.addEventListener('canplaythrough', triggerPlay);
				}
			});

			function triggerPlay() {
				const timeWrap = elem.querySelector('.time-music') as HTMLDivElement;
				if (audio.paused) {
					audio.addEventListener('timeupdate', () => {
						timeWrap.innerText = `${Math.floor(audio.currentTime / 60)}:${Math.floor(audio.currentTime % 60).toString().padStart(2, '0')}`;
					});
					playBtn.classList.remove('btn-success');
					playBtn.classList.add('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-stop"></i>`;
					lang('Zastavit', null, 1, 'actions')
						.then((response: AxiosResponse<string>) => {
							tooltip.setContent({
								'.tooltip-inner': response.data,
							});
						});
					// Reset playback
					audio.load();
					// Play
					audio.play();
				}
			}
		}
	}
}