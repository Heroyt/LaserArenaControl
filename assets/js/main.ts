import {setCookie, toAscii} from './includes/functions';
import {Tooltip} from 'bootstrap';
import route from './router';
import {PageInfo} from './interfaces/pageInfo';
import ActivityMonitor from './activityMonitor';
import {GameData} from './interfaces/gameInterfaces';
import {gateActions} from './components/gateActions';
import {initTooltips} from './includes/tooltips';
import {initCollapse} from './includes/collapse';
import {initAutoSaveForm} from './includes/autoSaveForm';
import {gameTimer} from './includes/gameTimer';
import {initLoaders} from './loaders';
import {initSelectDescription} from './includes/selectDescription';

declare global {
	const page: PageInfo;
	let activeGame: GameData | null;
}

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register('/dist/service-worker.js', {scope: '/'}).then(registration => {
			console.log('SW registered: ', registration);
		}).catch(registrationError => {
			console.log('SW registration failed: ', registrationError);
		});
	});
}

activeGame = null;

window.addEventListener('load', () => {

	// Select description
	initSelectDescription();

	// Loaders
	initLoaders();

	// Tooltips
	initTooltips();

	// Collapse
	initCollapse();

	// Auto-save
	initAutoSaveForm();

	// Toggles
	(document.querySelectorAll('[data-toggle="submit"]') as NodeListOf<HTMLElement>).forEach(element => {
		element.addEventListener('change', () => {
			// @ts-ignore
			(element.findParentElement('form') as HTMLFormElement).submit();
		});
	});
	(document.querySelectorAll('[data-toggle="shuffle"]') as NodeListOf<HTMLButtonElement>).forEach(element => {
		if (element.title) {
			new Tooltip(element);
		}
		if (!element.dataset.target) {
			// Missing target
			return;
		}
		const targets = document.querySelectorAll(element.dataset.target) as NodeListOf<HTMLSelectElement>;
		if (targets.length === 0) {
			// Invalid target
			return;
		}
		element.addEventListener('click', () => {
			targets.forEach(target => {
				const options = target.querySelectorAll('option') as NodeListOf<HTMLOptionElement>;
				if (options.length === 0) {
					return;
				}
				const index = Math.floor(Math.random() * options.length);
				target.value = options[index].value;
				const e = new Event('change', {bubbles: true});
				target.dispatchEvent(e);
			});
		});
	});
	document.querySelectorAll<HTMLElement>('[data-set-lang]').forEach(element => {
		const lang = element.dataset.setLang;
		element.addEventListener('click', e => {
			e.preventDefault();
			setCookie('lang', lang);
			window.location.reload();
		});
	});

	// Pages
	route(page);

	// Game timer
	gameTimer();
	const activityMonitor = new ActivityMonitor();
	activityMonitor.reset(); // Start the activity monitor

	// Setting a game to gate
	gateActions();
});


// @ts-ignore
String.prototype.removeDiacritics = function () {
	return toAscii(this);

};

// @ts-ignore
String.prototype.hashCode = function () {
	let hash = 0, i, chr;
	if (this.length === 0) return hash;
	for (i = 0; i < this.length; i++) {
		chr = this.charCodeAt(i);
		hash = ((hash << 5) - hash) + chr;
		hash |= 0; // Convert to 32bit integer
	}
	return hash;
};


/**
 * Finds a parent element
 */
// @ts-ignore
Element.prototype.findParentElement = function (elemName: string): HTMLElement {
	let currElem = this;
	while (currElem.tagName.toLowerCase() !== elemName.toLowerCase()) {
		currElem = currElem.parentNode;
		if (currElem === document.body) {
			return null;
		}
	}
	return currElem;
};
/**
 * Finds a parent element
 */
// @ts-ignore
Element.prototype.findParentElementByClassName = function (className: string): HTMLElement {
	let currElem = this;
	while (!currElem.classList.contains(className)) {
		currElem = currElem.parentNode;
		if (currElem === document.body) {
			return null;
		}
	}
	return currElem;
};