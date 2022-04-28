import axios from "axios";
import {gameTimer} from "../functions";
import EventServerInstance from "../EventServer";

let reloadTimeout = null;

export default function initGate() {

	if (reloadTimer && reloadTimer > 0) {
		reloadTimeout = setTimeout(loadContent, reloadTimer * 1000);
	}

	// WebSocket event listener
	EventServerInstance.addEventListener(['game-imported', 'game-started', 'game-loaded', 'gate-reload'], loadContent);

	tipsRotations();
}

function loadContent() {
	const container = document.querySelector('main');
	if (!container) {
		return;
	}
	axios.get('/gate')
		.then(response => {
			clearTimeout(reloadTimeout);
			if (response.headers['x-reload-time']) {
				const time = parseInt(response.headers['x-reload-time']);
				if (!isNaN(time)) {
					reloadTimeout = setTimeout(loadContent, time * 1000);
				}
			}
			container.innerHTML = response.data;
			const meta = container.querySelector('meta[name="container-classes"]');
			if (meta) {
				container.className = meta.getAttribute('content');
			}
			gameTimer();
		})
		.catch(response => {
			console.error(response);
		});
}

function tipsRotations() {
	let counter = 0;
	const tipContent = document.querySelector('.tip .content');
	if (!tips || !tipContent) {
		return;
	}
	setInterval(() => {
		tipContent.innerText = tips[counter];
		counter = (counter + 1) % tips.length;
	}, 10000);
}