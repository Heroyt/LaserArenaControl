import {Toast} from "bootstrap";
import EventServerInstance from "../EventServer";

let activityTimer;
let isActive = true;
let newGame = false;
let reloadUrl = window.location.href;

/**
 *
 * @param {String|null} url
 */
export default function initResultsReload(url = null) {

	if (url) {
		reloadUrl = url;
	}

	// Activity timer detects any user activity to prevent site-reload when not wanted
	document.addEventListener("mousemove", resetActivityTimer, false);
	document.addEventListener("mousedown", resetActivityTimer, false);
	document.addEventListener("keypress", resetActivityTimer, false);
	document.addEventListener("touchmove", resetActivityTimer, false);
	startActivityTimer();

	// WebSocket event listener
	EventServerInstance.addEventListener("game-imported", triggerNewGame);
}

function triggerNewGame() {
	const toasts = document.getElementById('toasts');
	newGame = true;
	const toast = document.createElement('div');
	toast.classList.add('toast');
	toast.role = 'alert';
	toast.ariaLive = "assertive";
	toast.ariaAtomic = "true";
	toast.innerHTML = `<div class="toast-header"><strong class="me-auto">Upozornění</strong></div><div class="toast-body text-start"><p>Byla detekována nová hra</p><p><a href="${reloadUrl}" class="btn btn-primary">Načíst</a></p></div>`;
	toasts.appendChild(toast);
	const toastObj = new Toast(toast, {
		autohide: false
	});
	toastObj.show();
	if (!isActive) {
		setTimeout(() => {
			if (reloadUrl === window.location.href) {
				window.location.reload();
			} else {
				window.location = reloadUrl;
			}
		}, 10000);
	}
}

function startActivityTimer() {
	activityTimer = window.setTimeout(() => {
		isActive = false;
		if (newGame) {
			if (reloadUrl === window.location.href) {
				window.location.reload();
			} else {
				window.location = reloadUrl;
			}
		}
	}, 30000);
}

function resetActivityTimer() {
	isActive = true;
	startActivityTimer();
}