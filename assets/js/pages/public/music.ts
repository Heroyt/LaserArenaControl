import {initMusicPlay} from '../../components/musicPlay';

export default function initPublicMusic() {
	document.querySelectorAll<HTMLElement>('.music-mode')
		.forEach(initMusicPlay);
}