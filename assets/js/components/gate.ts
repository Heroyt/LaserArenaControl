import {prepareFetch, processResponse} from '../includes/apiClient';
import {getGameHighlights} from '../api/endpoints/games';
import GateScreen from '../gate/gateScreen';

declare global {
	let tips: string[];
	const tipsDefault: string[];
	let reloadTimer: number;
	const timerOffset: number;
}

const loadedAssets = new Set<string>;
const loadedScreens = new Map<string, { default: new() => GateScreen }>;

let lastScreen: GateScreen | null = null;

/**
 * @type {boolean} If the tips component is displaying game highlights
 */
let tipsHighlights: boolean = false;

let container: HTMLElement = document.querySelector('main');

function removePreviousContent(): void {
	const elements = container.querySelectorAll('.content') as NodeListOf<HTMLDivElement>;
	for (let i = 0; i < elements.length - 1; i++) {
		elements[i].remove();
	}
}

/**
 * Load gate content, replace the current and run animations if necessary
 * @param path Gate URL path to load.
 * @param reloadTimeout Object that stores the reload timeout for gate. The timeout will be updated if necessary.
 */
export function loadContent(path: string, reloadTimeout: { timeout: null | NodeJS.Timeout }) {
	if (!container) {
		container = document.querySelector('main');
	}
	if (!container) {
		return;
	}
	const contentActive = container.querySelector('.content') as HTMLDivElement;
	if (!contentActive) {
		return;
	}

	const contentNew = document.createElement('div');
	// Load content
	prepareFetch(path, 'GET')
		.then(async (response) => {

			// Setup next auto-reload
			clearTimeout(reloadTimeout.timeout);
			console.log(Array.from(response.headers.entries()));
			if (response.headers.has('x-reload-time')) {
				const time = parseInt(response.headers.get('x-reload-time'));
				console.log('Reload timer', time);
				if (!isNaN(time)) {
					reloadTimeout.timeout = setTimeout(() => {
						loadContent(path, reloadTimeout);
					}, time * 1000);
				}
			}

			// Copy content
			if (response.headers.get('Content-Type') === 'application/json') {
				console.log('Invalid content type');
				return;
			}
			contentNew.innerHTML = await processResponse(response.headers.get('Content-Type'), response);

			// Find new container classes
			const meta = contentNew.querySelector<HTMLMetaElement>('meta[name="container-classes"]');
			if (meta) {
				contentNew.className += meta.getAttribute('content');
			}

			await initContent(contentNew, contentActive);
		})
		.catch(response => {
			console.error(response);
		});
}

export async function initContent(content: HTMLDivElement, previous: HTMLDivElement | null = null) {
	if (!container) {
		container = document.querySelector('main');
	}

	// Reset tips
	tips = tipsDefault;
	tipsHighlights = false;

	// Store information about loaded styles
	document.head.querySelectorAll<HTMLLinkElement>('link.add-style').forEach(link => {
		loadedAssets.add(link.href);
		// Remove class to prevent duplicate initialization
		link.classList.remove('add-style');
	});

	const scriptMeta = content.querySelector<HTMLMetaElement>('meta[name="add-script"]');
	let moduleClass: GateScreen = null;
	if (scriptMeta) {
		console.log(scriptMeta.content);

		// Cache loaded modules to prevent loading JS scripts all the time
		let module: { default: new () => GateScreen };
		if (loadedScreens.has(scriptMeta.content)) {
			module = loadedScreens.get(scriptMeta.content);
		} else {
			module = await import(scriptMeta.content);
			// Cache module for later use to prevent further import() call
			loadedScreens.set(scriptMeta.content, module);
		}

		// Initialize module
		moduleClass = new module.default;
		moduleClass.init(content, removePreviousContent);

		// Check for module changes
		if (lastScreen && moduleClass.isSame(lastScreen)) {
			console.log('Skipping module - the screen is identical');
			return;
		}
	} else {
		console.error('No add-script found!');
	}

	// Load extra styles
	const styles = content.querySelectorAll<HTMLMetaElement>('meta[name="add-style"]');
	styles.forEach(styleMeta => {
		// Check if not already loaded
		if (loadedAssets.has(styleMeta.content)) {
			return;
		}

		// Add new styles
		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = styleMeta.content;
		document.head.appendChild(link);

		// Cache information about loaded styles
		loadedAssets.add(styleMeta.content);
	});

	container.appendChild(content);

	// Swap modules
	if (moduleClass) {
		if (lastScreen) {
			lastScreen.animateOut();
		}
		moduleClass.animateIn();
		const timer = container.querySelector<HTMLDivElement>('.timer');
		if (timer) {
			if (moduleClass.showTimer()) {
				timer.style.display = 'initial';
			} else {
				timer.style.display = 'none';
			}
		}
	}

	lastScreen = moduleClass;
}

/**
 * Load highlights for a current game and show them instead of tips
 * @param wrapper Results parent element
 */
export async function replaceTipsWithHighlights(wrapper: HTMLElement | Document = document) {
	const gameInfo: HTMLElement = wrapper.querySelector('[data-game]');
	console.log(gameInfo, tipsHighlights);
	// Check if we can get the game code or if the highlights are not already displayed
	if (!gameInfo || tipsHighlights) {
		return;
	}

	const code = gameInfo.dataset.game;
	console.log(gameInfo, code);

	// Load highlights for game
	const highlightsData = await getGameHighlights(code);

	// Parse highlights.
	// Highlights contain player names with their inflection, where the inflection is optional - '(name)<inflection>'
	const highlights: string[] = [];
	highlightsData.forEach(highlight => {
		highlights.push(highlight.description.replace(/@([^@]+)@(?:<([^@]+)>)?/g, (_, group1: string, group2: string | undefined) => {
			return `<strong class="player-name">${group2 ? group2 : group1}</strong>`;
		}));
	});

	// Replace tips with highlights
	tips = highlights;
	tipsHighlights = true;
}

/**
 * Initialize rotating tips component
 */
export function tipsRotations() {
	let counter = 0;
	const tipWrapper = document.querySelector('.tip') as HTMLDivElement;
	if (!tips || !tipWrapper) {
		return;
	}

	// Rotate tips
	setInterval(() => {
		const tipActive = tipWrapper.querySelectorAll('.content') as NodeListOf<HTMLSpanElement>;
		const tipNew = document.createElement('span');
		tipNew.classList.add('content', 'next');

		// Check if we can get the next tip
		if (!tips[counter]) {
			if (tips.length === 0) {
				tips = tipsDefault;
			}
			counter = counter % tips.length;
		}

		// Add a new tip
		tipNew.innerHTML = tips[counter];
		tipWrapper.appendChild(tipNew);

		// Animate old tips out
		tipActive.forEach(el => {
			el.classList.remove('active', 'next');
			el.classList.add('prev');
		});

		counter = (counter + 1) % tips.length;

		// Animate the new tip in
		setTimeout(() => {
			tipNew.classList.remove('next');
			tipNew.classList.add('active');
			tipActive.forEach(el => {
				el.remove();
			});
		}, 1000);
	}, 10000);
}