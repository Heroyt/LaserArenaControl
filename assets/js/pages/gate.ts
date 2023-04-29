import EventServerInstance from "../EventServer";
import {loadContent, tipsRotations} from "../components/gate";

export let reloadTimeout: { timeout: null | NodeJS.Timeout } = {timeout: null};
export default function initGate() {

	if (reloadTimer && reloadTimer > 0) {
		reloadTimeout.timeout = setTimeout(() => {
			loadContent('/gate', reloadTimeout);
		}, reloadTimer * 1000);
	}

	// WebSocket event listener
	EventServerInstance.addEventListener(['game-imported', 'game-started', 'game-loaded', 'gate-reload'], () => {
		loadContent('/gate', reloadTimeout);
	});

	tipsRotations();
}