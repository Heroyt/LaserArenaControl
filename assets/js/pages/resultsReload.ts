import EventServerInstance from '../EventServer';
import {triggerNotification} from '../includes/notifications';

let activityTimer: NodeJS.Timeout | number;
let isActive: boolean = true;
let newGame: boolean = false;
let reloadUrl: string = window.location.href;

/**
 *
 * @param {String|null} url
 */
export default function initResultsReload(url: string | null = null): void {

	if (url) {
		reloadUrl = url;
	}

	// Activity timer detects any user activity to prevent site-reload when not wanted
	document.addEventListener('mousemove', resetActivityTimer, false);
	document.addEventListener('mousedown', resetActivityTimer, false);
	document.addEventListener('keypress', resetActivityTimer, false);
	document.addEventListener('touchmove', resetActivityTimer, false);
	startActivityTimer();

	// WebSocket event listener
	EventServerInstance.addEventListener('game-imported', triggerNewGame);
}

function triggerNewGame() {
	triggerNotification(
		{
			title: 'Upozornění',
			type: 'info',
			content: `<p>Byla detekována nová hra</p><p><a href="${reloadUrl}" class="btn btn-primary">Načíst</a></p>`,
		},
		false,
	);
	if (!isActive) {
		setTimeout(() => {
			if (reloadUrl === window.location.href) {
				window.location.reload();
			} else {
				window.location.href = reloadUrl;
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
				window.location.href = reloadUrl;
			}
		}
	}, 30000);
}

function resetActivityTimer() {
	isActive = true;
	startActivityTimer();
}