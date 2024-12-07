import {startLoading, stopLoading} from '../../loaders';
import {toAscii} from '../../includes/functions';
import Sortable from 'sortablejs';
import {Music} from '../../interfaces/gameInterfaces';
import autocomplete, {AutocompleteItem} from 'autocompleter';
import {fetchPost, ResponseError} from '../../includes/apiClient';
import {
	deleteMusic,
	MusicUploadResponse,
	uploadMusicArmed,
	uploadMusicEnding,
	uploadMusicIntro,
} from '../../api/endpoints/settings/music';
import {initTooltips} from '../../includes/tooltips';
import {initImageUploadPreview} from '../../includes/imageUploadPreview';
import {initMusicPlay} from '../../components/musicPlay';
import {initCollapse} from '../../includes/collapse';
import {triggerNotificationError} from '../../includes/notifications';


export default function initMusicSettings() {
	const uploadInput = document.getElementById('media') as HTMLInputElement;
	const uploadForm = document.getElementById('upload-form') as HTMLFormElement;

	const musicForm = document.getElementById('music-settings-form') as HTMLFormElement;
	const musicWrapper = document.getElementById('musicInputsWrapper') as HTMLDivElement;

	const notices = document.getElementById('notices') as HTMLDivElement;
	const musicTemplate = document.getElementById('musicInputTemplate').innerHTML;

	const musicGroups: Set<string> = new Set;

	if (uploadForm && uploadInput) {
		uploadForm.addEventListener('submit', e => {
			e.preventDefault();

			startLoading();
			upload(0, uploadInput.files);
		});
	}

	(document.querySelectorAll('.music-input') as NodeListOf<HTMLDivElement>).forEach(initMusic);

	// noinspection JSUnusedLocalSymbols
	const sortableMusic = new Sortable(musicWrapper, {
		handle: '.counter',
		draggable: '.music-input',
		ghostClass: 'cursor-grabbing',
		onSort: recountMusic,
	});

	const addPlaylistBtn = document.getElementById('add-playlist') as HTMLButtonElement;
	const playlistWrapper = document.getElementById('playlist-wrapper') as HTMLDivElement;
	const playlistTemplate = document.getElementById('playlistTemplate') as HTMLTemplateElement;
	let playlistCounter = 0;
	if (addPlaylistBtn && playlistWrapper && playlistTemplate) {
		addPlaylistBtn.addEventListener('click', () => {
			const tmp = document.createElement('div');
			tmp.innerHTML = playlistTemplate.innerHTML.replaceAll('#id#', `new-${playlistCounter}`);
			const playlist = tmp.firstElementChild as HTMLDivElement;
			playlistCounter++;
			playlistWrapper.appendChild(playlist);
		});

		musicForm.addEventListener('autosaved', (e: CustomEvent<{ playlistIds: Record<string, string> }>) => {
			Object.entries(e.detail.playlistIds).forEach(([original, id]) => {
				const playlist = musicForm.querySelector<HTMLDivElement>(`.playlist[data-id="${original}"]`);
				if (!playlist) {
					return;
				}

				playlist.dataset.id = id;
				playlist.setAttribute('data-id', id);

				const inputs = playlist.querySelectorAll<HTMLInputElement>('input');
				inputs.forEach(input => {
					input.name = input.name.replace(original, id);
				});
			});
		});
	}

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

		fetchPost(uploadForm.getAttribute('action'), data)
			.then(handleResponse)
			.catch(handleResponse);

		async function handleResponse(response: MusicUploadResponse | ResponseError) {
			let data: MusicUploadResponse;
			if (response instanceof ResponseError) {
				data = await response.getDataFromResponse();
			} else {
				data = response;
			}

			if (data) {
				if (data.errors) {
					data.errors.forEach(error => {
						addAlert('danger', error);
					});
				}
				if (data.notices) {
					data.notices.forEach(notice => {
						addAlert(notice.type, notice.content);
					});
				}
				if (data.music) {
					data.music.forEach(info => {
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
		div.classList.add('alert', 'alert-' + type);
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

	function refreshMusicGroups() {
		musicGroups.clear();

		(document.querySelectorAll('.music-input') as NodeListOf<HTMLDivElement>).forEach(music => {
			const group = music.querySelector('.music-group') as HTMLInputElement;
			if (group.value) {
				musicGroups.add(group.value);
			}
		});
	}

	function initMusic(elem: HTMLDivElement) {
		initImageUploadPreview(elem);
		initCollapse(elem);
		const id = parseInt(elem.dataset.id);

		const group = elem.querySelector('.music-group') as HTMLInputElement;
		if (group.value) {
			musicGroups.add(group.value);
		}
		group.addEventListener('change', refreshMusicGroups);
		autocomplete({
			input: group,
			emptyMsg: 'Nová skupina',
			minLength: 1,
			preventSubmit: 1,
			fetch: (search, update) => {
				search = toAscii(search.toLocaleLowerCase());
				const data: AutocompleteItem[] = [];
				musicGroups.forEach(value => {
					if (toAscii(value.toLocaleLowerCase()).startsWith(search)) {
						data.push({label: value});
					}
				});
				update(data);
			},
			onSelect: item => {
				group.value = item.label;
				refreshMusicGroups();
			},
		});

		const deleteBtn = elem.querySelector('.remove') as HTMLButtonElement;
		if (deleteBtn) {
			deleteBtn.addEventListener('click', () => {
				startLoading();
				deleteMusic(id)
					.then(() => {
						elem.remove();
						stopLoading();
						recountMusic();
					})
					.catch(e => {
						triggerNotificationError(e);
						stopLoading(false);
					});
			});
		}
		initMusicPlay(elem.querySelector<HTMLDivElement>('.music-input-group'));

		const armedGroup = elem.querySelector<HTMLDivElement>('.armed-group');
		if (armedGroup) {
			const armedInput = armedGroup.querySelector<HTMLInputElement>('.armed-upload');
			const playBtn = armedGroup.querySelector<HTMLButtonElement>('.play-music');
			initMusicPlay(armedGroup);
			if (armedInput && playBtn) {
				armedInput.addEventListener('change', () => {
					if (armedInput.files.length === 0) {
						return;
					}
					startLoading();
					uploadMusicArmed(id, armedInput.files[0])
						.then(response => {
							armedGroup.querySelector<HTMLSpanElement>('label span').innerText = response.values.name;
							playBtn.dataset.file = response.values.url;
							playBtn.dispatchEvent(new CustomEvent('reload'));
							stopLoading(true);
						})
						.catch(e => {
							stopLoading(false);
							triggerNotificationError(e);
						});
				});
			}
		}
		const introGroup = elem.querySelector<HTMLDivElement>('.intro-group');
		if (introGroup) {
			const introInput = introGroup.querySelector<HTMLInputElement>('.intro-upload');
			const playBtn = introGroup.querySelector<HTMLButtonElement>('.play-music');
			initMusicPlay(introGroup);
			if (introInput && playBtn) {
				introInput.addEventListener('change', () => {
					if (introInput.files.length === 0) {
						return;
					}
					startLoading();
					uploadMusicIntro(id, introInput.files[0])
						.then(response => {
							introGroup.querySelector<HTMLSpanElement>('label span').innerText = response.values.name;
							playBtn.dataset.file = response.values.url;
							playBtn.dispatchEvent(new CustomEvent('reload'));
							stopLoading(true);
						})
						.catch(e => {
							stopLoading(false);
							triggerNotificationError(e);
						});
				});
			}
		}
		const endingGroup = elem.querySelector<HTMLDivElement>('.ending-group');
		if (endingGroup) {
			const endingInput = endingGroup.querySelector<HTMLInputElement>('.ending-upload');
			const playBtn = endingGroup.querySelector<HTMLButtonElement>('.play-music');
			initMusicPlay(endingGroup);
			if (endingInput && playBtn) {
				endingInput.addEventListener('change', () => {
					if (endingInput.files.length === 0) {
						return;
					}
					startLoading();
					uploadMusicEnding(id, endingInput.files[0])
						.then(response => {
							endingGroup.querySelector<HTMLSpanElement>('label span').innerText = response.values.name;
							playBtn.dataset.file = response.values.url;
							playBtn.dispatchEvent(new CustomEvent('reload'));
							stopLoading(true);
						})
						.catch(e => {
							stopLoading(false);
							triggerNotificationError(e);
						});
				});
			}
		}
	}
}