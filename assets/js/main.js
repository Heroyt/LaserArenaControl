import {formatPhoneNumber, gameTimer, getLink, initAutoSaveForm, initTooltips} from './functions.js';
import axios from 'axios';
import {startLoading, stopLoading} from "./loaders";
import * as bootstrap from "bootstrap";
import jscolor from "@eastdesire/jscolor";

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.get['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

jscolor.presets.default = {
	format: 'hex',
	uppercase: false,
};

window.addEventListener("load", () => {

	// Auto-format tel
	document.querySelectorAll('input[type="tel"]').forEach(input => {
		if (input.classList.contains('not-format')) {
			return;
		}
		input.value = formatPhoneNumber(input.value);
		input.addEventListener("keydown", () => {
			input.value = formatPhoneNumber(input.value);
		});
		input.addEventListener("change", () => {
			input.value = formatPhoneNumber(input.value);
		});
	});

	// Select description
	document.querySelectorAll('.select-description').forEach(element => {
		if (!element.dataset.target) {
			// Missing target
			console.log('Missing target');
			return;
		}

		const target = document.querySelector(element.dataset.target);
		console.log(target, target.value);
		if (!target) {
			// Invalid target
			console.log('Invalid target');
			return;
		}
		const option = target.querySelector(`option[value="${target.value}"]`);
		if (option && option.dataset.description) {
			console.log(option, target.dataset.description);
			element.innerText = option.dataset.description;
		} else {
			element.innerText = '';
		}
		target.addEventListener('change', () => {
			const option = target.querySelector(`option[value="${target.value}"]`);
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
	document.querySelectorAll('[data-toggle="submit"]').forEach(element => {
		element.addEventListener("change", () => {
			element.findParentElement("form").submit();
		});
	});
	document.querySelectorAll('[data-toggle="shuffle"]').forEach(element => {
		if (element.title) {
			new bootstrap.Tooltip(element);
		}
		if (!element.dataset.target) {
			// Missing target
			return;
		}
		const targets = document.querySelectorAll(element.dataset.target);
		if (targets.length === 0) {
			// Invalid target
			return;
		}
		element.addEventListener("click", () => {
			targets.forEach(target => {
				const options = target.querySelectorAll('option');
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
	console.log(page.routeName);
	if (page.routeName && page.routeName === 'settings-print') {
		import(
			/* webpackChunkName: "resultsReload" */
			'./pages/settings/print'
			).then(module => {
			module.default();
		});
	} else if (page.routeName && page.routeName === 'settings-vests') {
		import(
			/* webpackChunkName: "resultsReload" */
			'./pages/settings/vests'
			).then(module => {
			module.default();
		});
	} else if (page.routeName && page.routeName === 'games-list') {
		console.log('games-list');
		Promise.all(
			[
				import(
					/* webpackChunkName: "resultsReload" */
					'./pages/resultsReload'
					),
				import(
					/* webpackChunkName: "gamesList" */
					'./pages/gamesList'
					)
			]
		)
			.then(([resultsReload, gamesList]) => {
				console.log(resultsReload, gamesList);
				resultsReload.default();
				gamesList.default();
			});
	} else if (page.routeName && (page.routeName === 'results' || page.routeName === 'results-game')) {
		import(
			/* webpackChunkName: "resultsReload" */
			'./pages/resultsReload'
			).then(module => {
			module.default(getLink(['results']));
		});
	} else if (page.routeName && page.routeName === 'gate') {
		import(/* webpackChunkName: "gate" */ './pages/gate').then(module => {
			module.default();
		})
	} else if (page.routeName && page.routeName === 'dashboard') {
		import(/* webpackChunkName: "dashboard" */ './pages/newGame').then(module => {
			module.default();
		})
	} else if (page.routeName && page.routeName === 'settings-music') {
		import(/* webpackChunkName: "musicSettings" */ './pages/settings/music').then(initMusicSettings => {
			initMusicSettings.default();
		});
	}

	// Game timer
	gameTimer();

	// Setting a game to gate
	document.querySelectorAll('[data-toggle="gate"]').forEach(btn => {
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
	document.querySelectorAll('[data-toggle="gate-loaded"]').forEach(btn => {
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
});