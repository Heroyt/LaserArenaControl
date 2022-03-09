import {Toast} from "bootstrap";

let activityTimer;
let isActive = true;
let newGame = false;

export default function initPrintPage() {

	// Activity timer detects any user activity to prevent site-reload when not wanted
	document.addEventListener("mousemove", resetActivityTimer, false);
	document.addEventListener("mousedown", resetActivityTimer, false);
	document.addEventListener("keypress", resetActivityTimer, false);
	document.addEventListener("touchmove", resetActivityTimer, false);
	startActivityTimer();

	// WebSocket event listener
	const ws = new WebSocket(webSocketEventURI);
	ws.onmessage = e => {
		if (e.data.trim() === 'game-imported') {
			triggerNewGame();
		}
	};
}

function triggerNewGame() {
	const toasts = document.getElementById('toasts');
	newGame = true;
	const toast = document.createElement('div');
	toast.classList.add('toast');
	toast.role = 'alert';
	toast.ariaLive = "assertive";
	toast.ariaAtomic = "true";
	toast.innerHTML = `<div class="toast-header"><strong class="me-auto">Upozornění</strong></div><div class="toast-body text-start"><p>Byla detekována nová hra</p><p><a href="${window.location.href}" class="btn btn-primary">Načíst</a></p></div>`;
	toasts.appendChild(toast);
	const toastObj = new Toast(toast, {
		autohide: false
	});
	toastObj.show();
	if (!isActive) {
		setTimeout(() => {
			window.location.reload();
		}, 10000);
	}
}

function startActivityTimer() {
	activityTimer = window.setTimeout(() => {
		isActive = false;
		if (newGame) {
			window.location.reload();
		}
	}, 30000);
}

function resetActivityTimer() {
	isActive = true;
	startActivityTimer();
}