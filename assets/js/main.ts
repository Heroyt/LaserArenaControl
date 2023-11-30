import {gameTimer, initAutoSaveForm, initTooltips} from './functions';
import axios from 'axios';
import {startLoading, stopLoading} from "./loaders";
import * as bootstrap from "bootstrap";
// @ts-ignore
import jscolor from "@eastdesire/jscolor";
import route from "./router";
import {PageInfo} from "./interfaces/pageInfo";
import ActivityMonitor from "./activityMonitor";
import {GameData} from "./interfaces/gameInterfaces";

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.get['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

jscolor.presets.default = {
	format: 'hex',
	uppercase: false,
};

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

window.addEventListener("load", () => {

	// Select description
	(document.querySelectorAll('.select-description') as NodeListOf<HTMLElement>).forEach(element => {
		if (!element.dataset.target) {
			// Missing target
			console.log('Missing target');
			return;
		}

		const target = document.querySelector(element.dataset.target) as HTMLSelectElement;
		console.log(target, target.value);
		if (!target) {
			// Invalid target
			console.log('Invalid target');
			return;
		}
		const option = target.querySelector(`option[value="${target.value}"]`) as HTMLOptionElement;
		if (option && option.dataset.description) {
			console.log(option, target.dataset.description);
			element.innerText = option.dataset.description;
		} else {
			element.innerText = '';
		}
		target.addEventListener('change', () => {
			const option = target.querySelector(`option[value="${target.value}"]`) as HTMLOptionElement;
			if (option && option.dataset.description) {
				console.log(option, target.dataset.description);
				element.innerText = option.dataset.description;
			} else {
				element.innerText = '';
			}
		});
	});

	// Tooltips
	initTooltips(document);

	// Auto-save
	initAutoSaveForm();

	// Toggles
	(document.querySelectorAll('[data-toggle="submit"]') as NodeListOf<HTMLElement>).forEach(element => {
		element.addEventListener("change", () => {
			// @ts-ignore
			(element.findParentElement("form") as HTMLFormElement).submit();
		});
	});
	(document.querySelectorAll('[data-toggle="shuffle"]') as NodeListOf<HTMLButtonElement>).forEach(element => {
		if (element.title) {
			new bootstrap.Tooltip(element);
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
		element.addEventListener("click", () => {
			targets.forEach(target => {
				const options = target.querySelectorAll('option') as NodeListOf<HTMLOptionElement>;
				if (options.length === 0) {
					return;
				}
				const index = Math.floor(Math.random() * options.length);
				target.value = options[index].value;
				const e = new Event("change", {bubbles: true});
				target.dispatchEvent(e);
			});
		});
	});

	// Pages
	route(page);

	// Game timer
	gameTimer();
    const activityMonitor = new ActivityMonitor();

	// Setting a game to gate
	(document.querySelectorAll('[data-toggle="gate"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		const id = btn.dataset.id;
		const system = btn.dataset.system;
		// Allow for tooltips
		if (btn.title) {
			new bootstrap.Tooltip(btn);
		}
		btn.addEventListener('click', () => {
			startLoading(true);
			axios
				.post('/gate/set/' + system, {
					game: id
				})
				.then(response => {
					stopLoading(true, true);
					if (btn.classList.contains('btn-danger')) {
						btn.classList.remove('btn-danger');
						btn.classList.add('btn-success');
					}
				})
				.catch(response => {
					stopLoading(false, true);
				});
		});
	});
	(document.querySelectorAll('[data-toggle="gate-loaded"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		const id = btn.dataset.id;
		const system = btn.dataset.system;
		// Allow for tooltips
		if (btn.title) {
			new bootstrap.Tooltip(btn);
		}
		btn.addEventListener('click', () => {
			startLoading(true);
			axios
				.post('/gate/loaded/' + system, {
					game: id
				})
				.then(response => {
					stopLoading(true, true);
					if (btn.classList.contains('btn-danger')) {
						btn.classList.remove('btn-danger');
						btn.classList.add('btn-success');
					}
				})
				.catch(response => {
					stopLoading(false, true);
				});
		});
	});
	(document.querySelectorAll('[data-toggle="gate-idle"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		const system = btn.dataset.system;
		// Allow for tooltips
		if (btn.title) {
			new bootstrap.Tooltip(btn);
		}
		btn.addEventListener('click', () => {
			startLoading(true);
			axios
				.post('/gate/idle/' + system)
				.then(response => {
					stopLoading(true, true);
					if (btn.classList.contains('btn-danger')) {
						btn.classList.remove('btn-danger');
						btn.classList.add('btn-success');
					}
				})
				.catch(response => {
					stopLoading(false, true);
				});
		});
	});
});