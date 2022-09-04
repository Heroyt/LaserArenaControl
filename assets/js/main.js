import {formatPhoneNumber, gameTimer, getLink, initAutoSaveForm, initTooltips} from './functions.js';
import axios from 'axios';
import flatpickr from "flatpickr";
import initPrintSettings from "./pages/settings/print";
import initResultsReload from "./pages/resultsReload";
import initGate from "./pages/gate";
import {startLoading, stopLoading} from "./loaders";
import * as bootstrap from "bootstrap";
import initVestsSettings from "./pages/settings/vests";
import initGamesList from "./pages/gamesList";

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

	// Datepicker
	document.querySelectorAll('input[type="date"]:not([data-input]), .date-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("date-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		let options = {
			defaultDate: value,
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentNode,
			wrap,
		};
		if (input.dataset.events) {
			const events = JSON.parse(input.dataset.events);
			options.enable = Object.keys(events);
		}
		flatpickr(input, options);
	});
	document.querySelectorAll('input[type="datetime"]:not([data-input]), .datetime-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("datetime-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		flatpickr(input, {
			defaultDate: value,
			dateFormat: "d.m.Y H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			time_24hr: true,
			appendTo: input.parentNode,
			wrap: wrap,
		});
	});
	document.querySelectorAll('input[type="time"]:not([data-input]), .time-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("time-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		flatpickr(input, {
			defaultDate: value,
			dateFormat: "H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			noCalendar: true,
			time_24hr: true,
			appendTo: input.parentNode,
			wrap: wrap,
			onOpen: (e) => {
				document.querySelectorAll('.numInput').forEach((pickerInput) => {
					pickerInput.name = "flatpickr[]";
					pickerInput.type = "number";
				});
			},
			onClose: (e) => {
				document.querySelectorAll('.numInput').forEach((pickerInput) => {
					pickerInput.type = "text";
				});
			}
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
		}
		target.addEventListener('change', () => {
			const option = target.querySelector(`option[value="${target.value}"]`);
			if (option && option.dataset.description) {
				console.log(option, target.dataset.description);
				element.innerText = option.dataset.description;
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
		initPrintSettings();
	} else if (page.routeName && page.routeName === 'settings-vests') {
		initVestsSettings();
	} else if (page.routeName && page.routeName === 'games-list') {
		initResultsReload();
		initGamesList();
	} else if (page.routeName && (page.routeName === 'results' || page.routeName === 'results-game')) {
		initResultsReload(getLink(['results']));
	} else if (page.routeName && page.routeName === 'gate') {
		initGate();
	} else if (page.routeName && page.routeName === 'dashboard') {
		const ws = new WebSocket('ws://' + window.location.hostname + ':9999');
		ws.onmessage = e => {
			console.log(e);
		};
		const input = document.getElementById('socket');
		if (input) {
			input.addEventListener('change', () => {
				ws.send(input.value + '\n');
			});
		}
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