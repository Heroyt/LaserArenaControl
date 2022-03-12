import axios from "axios";

let reloadTimeout = null;
let timerInterval = null;

export default function initGate() {

	if (reloadTimer && reloadTimer > 0) {
		reloadTimeout = setTimeout(loadContent, reloadTimer * 1000);
	}

	// WebSocket event listener
	const ws = new WebSocket(webSocketEventURI);
	ws.onmessage = e => {
		const message = e.data.trim();
		if (
			message === 'game-imported' ||
			message === 'game-started' ||
			message === 'game-loaded' ||
			message === 'gate-reload'
		) {
			loadContent();
		}
	};

	tipsRotations();
	gameTimer();
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

function gameTimer() {
	clearInterval(timerInterval);
	const time = document.querySelector('.time');
	if (!time) {
		return;
	}
	let start = parseInt(time.dataset.start);
	let length = parseInt(time.dataset.length);
	if (isNaN(start) || isNaN(length)) {
		return;
	}
	let endDate = (start + length);
	console.log(endDate);
	timerInterval = setInterval(() => {
		const remaining = endDate - (Date.now() / 1000);
		if (remaining < 0) {
			time.innerHTML = "00:00";
			return;
		}
		const minutes = Math.floor(remaining / 60).toString().padStart(2, '0');
		const seconds = Math.floor(remaining % 60).toString().padStart(2, '0');
		time.innerHTML = `${minutes}:${seconds}`;
	}, 50);
}