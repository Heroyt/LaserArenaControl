import EventServerInstance from '../EventServer';
import {getLoadedGame} from '../api/endpoints/games';

declare global {
	let timerInterval: ReturnType<typeof setInterval> | null;
}

/**
 * Initialize a timer displaying the remaining game time
 */
export function gameTimer() {
	clearInterval(timerInterval);
	const times = document.querySelectorAll<HTMLDivElement>('.time:not(.initialized)');
	const showAfterTimes: { [index: number]: number } = {};
	if (times.length === 0) {
		return;
	}

	// Auto-reload timer on game started
	EventServerInstance.addEventListener(['game-started', 'game-imported', 'game-loaded'], loadGameInfo);

	let reloadInterval: NodeJS.Timeout | undefined; // Forcibly reload every 30 seconds in case of missed events
	const setupReloadInterval = () => {
		// If the timer is already running, clear it.
		// This will happen if the game info is reloaded due to any event.
		// We can restart the timer and fetch fresh data after another 30 seconds.
		if (reloadInterval) {
			clearTimeout(reloadInterval);
		}
		reloadInterval = setTimeout(() => {
			loadGameInfo();
		}, 30_000);
	};

	let offset = 0;
	const serverTime = parseInt(times[0].dataset.servertime);
	console.log(times[0].dataset.servertime, serverTime);
	//offset = (Date.now() / 1000) - (isNaN(serverTime) ? 0 : serverTime);
	times.forEach((time, key) => {
		let showAfter = parseInt(time.dataset.showafter);
		if (isNaN(showAfter)) {
			showAfter = 30;
		}
		showAfter *= 60; // Convert to seconds
		showAfterTimes[key] = showAfter;
	});

	let start = parseInt(times[0].dataset.start);
	let length = parseInt(times[0].dataset.length);
	let endDate = 0;
	if (isNaN(start) || isNaN(length)) {
		loadGameInfo();
		if (isNaN(start) || isNaN(length)) {
			return;
		}
	}
	endDate = (start + length);
	if (timerOffset && !isNaN(timerOffset)) {
		endDate += timerOffset;
	}

	for (const time of times) {
		time.classList.add('initialized');
	}

	startTimer();
	setupReloadInterval();

	function startTimer() {
		console.log('Starting timer...', endDate, offset);
		timerInterval = setInterval(() => {
			const remaining = endDate - (Date.now() / 1000) + offset;
			times.forEach((time, key) => {
				if (remaining > (showAfterTimes[key] ?? 1800)) {
					time.classList.add('d-none');
				} else {
					time.classList.remove('d-none');
				}
				if (remaining < 0) {
					time.innerHTML = '00:00';
					return;
				}
			});
			if (remaining > 0) {
				const minutes = Math.floor(remaining / 60).toString().padStart(2, '0');
				const seconds = Math.floor(remaining % 60).toString().padStart(2, '0');
				times.forEach(time => {
					time.innerHTML = `${minutes}:${seconds}`;
				});
			}
		}, 50);
	}

	/**
	 * Set the timers to the current game status
	 */
	function loadGameInfo() {
		getLoadedGame()
			.then(response => {
				if (response.ended) {
					activeGame = null;
					times.forEach(time => {
						time.dataset.start = '0';
						time.dataset.length = '0';
						setTimes(time);
					});
					return;
				}
				activeGame = response.game;
				const data = response;
				times.forEach(time => {
					if (data.currentServerTime) {
						time.dataset.servertime = data.currentServerTime.toString();
					}
					if (data.started && !data.ended && !data.finished && data.startTime) {
						time.dataset.start = data.startTime.toString();
						time.dataset.length = data.gameLength.toString();
					} else {
						time.dataset.start = '0';
						time.dataset.length = '0';
					}
					setTimes(time);
				});
			})
			.catch(err => {
				activeGame = null;
				console.log(err);
				if (err instanceof Promise) {
					err.then(e => {
						if (e.data) {
							console.error(e.data);
						} else {
							console.error(e);
						}
					});
					return;
				}
			})
			.finally(() => {
				document.dispatchEvent(new CustomEvent('active-game-data-loaded'));
				// Reset the reload interval
				setupReloadInterval();
			});
	}

	function setTimes(time: HTMLDivElement) {
		const parent = time.parentElement as HTMLDivElement;
		start = parseInt(time.dataset.start);
		length = parseInt(time.dataset.length);
		if (isNaN(start) || isNaN(length)) {
			start = 0;
			length = 0;
			endDate = Date.now() / 1000;
			return;
		}
		endDate = (start + length);
		if (timerOffset && !isNaN(timerOffset)) {
			endDate += timerOffset;
		}
		console.log(start, length, endDate, offset);
		if ((endDate - start) > 0) {
			startTimer();
			parent.style.display = 'initial';
		} else {
			parent.style.display = 'none';
		}
	}
}