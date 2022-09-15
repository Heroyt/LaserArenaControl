import {startLoading, stopLoading} from "../../loaders";
import axios from "axios";
import {initTooltips, lang} from "../../functions";
import Sortable from "sortablejs";

export default function initMusicSettings() {
	/**
	 * @type {HTMLInputElement}
	 */
	const uploadInput = document.getElementById('media');
	const uploadForm = document.getElementById('upload-form');

	const musicForm = document.getElementById('music-settings-form');
	const musicWrapper = document.getElementById('musicInputsWrapper');

	const notices = document.getElementById('notices');
	const musicTemplate = document.getElementById('musicInputTemplate').innerHTML;

	if (uploadForm && uploadInput) {
		uploadForm.addEventListener('submit', e => {
			e.preventDefault();

			startLoading();
			upload(0, uploadInput.files);
		});
	}

	document.querySelectorAll('.music-input').forEach(initMusic);

	const sortableMusic = new Sortable(musicWrapper, {
		handle: '.counter',
		draggable: '.music-input',
		ghostClass: 'cursor-grabbing',
		onSort: recountMusic,
	});

	/**
	 * @param index {Number}
	 * @param files {FileList}
	 */
	function upload(index, files) {
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

		/**
		 * @param response {AxiosResponse<{errors: String[], notices: {type: String, content: String}[], music: {id: Number, name: String, fileName: String, media: String}[]}>}
		 */
		function handleResponse(response) {
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
			upload(++index, files);
		}
	}

	/**
	 * @param type {String}
	 * @param content {String}
	 */
	function addAlert(type, content) {
		const div = document.createElement('div');
		div.classList.add('alert', 'alert-' + type)
		div.innerHTML = content;
		notices.appendChild(div);
	}

	/**
	 * @param info {{id: Number, name: String, fileName: String, media: String}}
	 */
	function addMusic(info) {
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
		initMusic(tmp.firstElementChild);
		initTooltips(tmp.firstElementChild);
		recountMusic();
	}

	function recountMusic() {
		let counter = 1;
		document.querySelectorAll('.music-input').forEach(elem => {
			elem.querySelector('.counter').innerText = counter.toString();
			elem.querySelector('.order-input').value = counter.toString();
			counter++;
		});
	}

	/**
	 * @param elem {HTMLDivElement}
	 */
	function initMusic(elem) {
		const id = elem.dataset.id;
		const deleteBtn = elem.querySelector('.remove');
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
		const playBtn = elem.querySelector('.play-music');
		if (playBtn) {
			const media = playBtn.dataset.file;
			let audio;
			playBtn.addEventListener('click', () => {
				playBtn.innerHTML = `<div class="spinner-grow spinner-grow-sm" role="status"><span class="visually-hidden">Loading...</span></div>`;
				if (!audio) {
					audio = new Audio(media);
				}

				if (!audio.paused) {
					playBtn.classList.add('btn-success');
					playBtn.classList.remove('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-play"></i>`;
					playBtn.title = lang('Přehrát', null, 1, 'actions');
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
				if (audio.paused) {
					playBtn.classList.remove('btn-success');
					playBtn.classList.add('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-stop"></i>`;
					playBtn.title = lang('Zastavit', null, 1, 'actions');
					// Reset playback
					audio.load();
					// Play
					audio.play();
				}
			}
		}
	}
}