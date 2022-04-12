import {Tooltip} from "bootstrap";
import {startLoading, stopLoading} from "./loaders";
import axios from "axios";
import EventServerInstance from "./EventServer";

String.prototype.replaceMultiple = function (chars) {
	let retStr = this;
	chars.forEach(ch => {
		retStr = retStr.replace(new RegExp(ch[0], 'g'), ch[1]);
	});
	return retStr;
};
String.prototype.decodeEntities = function () {
	const element = document.createElement('div');
	let str = this;
	str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
	str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
	element.innerHTML = str;
	str = element.textContent;
	element.textContent = '';
	return str;
}
/**
 * Finds a parent element
 *
 * @param elemName {String}
 */
Element.prototype.findParentElement = function (elemName) {
	let currElem = this;
	while (currElem.tagName.toLowerCase() !== elemName.toLowerCase()) {
		currElem = currElem.parentNode;
		if (currElem === document.body) {
			return null;
		}
	}
	return currElem;
}
/**
 * Finds a parent element
 *
 * @param className {String}
 *
 * @return {Element}
 */
Element.prototype.findParentElementByClassName = function (className) {
	let currElem = this;
	while (!currElem.classList.contains(className)) {
		currElem = currElem.parentNode;
		if (currElem === document.body) {
			return null;
		}
	}
	return currElem;
}

/**
 * @param {number} t Current time
 * @param {number} b Start time
 * @param {number} c Change in value
 * @param {number} d Duration
 *
 * @return {number}
 */
Math.easeInOutQuad = function (t, b, c, d) {
	t /= d / 2;
	if (t < 1) return c / 2 * t * t + b;
	t--;
	return -c / 2 * (t * (t - 2) - 1) + b;
};

/**
 * Smooth scroll element to y value
 *
 * @param {number} to Pixel value from top
 * @param {number} duration Time in ms
 */
window.scrollSmooth = function (to, duration) {
	let start = window.scrollY,
		change = to - start,
		currentTime = 0,
		increment = 10;

	const animateScroll = function () {
		currentTime += increment;
		window.scrollBy(0, Math.easeInOutQuad(currentTime, start, change, duration) - window.scrollY)
		if (currentTime < duration) {
			setTimeout(animateScroll, increment);
		}
	};
	animateScroll();
}

/**
 * Format a phone number to `000 000 000` format
 * @param {string} str
 * @returns {string|null}
 */
export function formatPhoneNumber(str) {
	//Filter only numbers from the input
	const plus = str[0] === '+';
	const cleaned = ('' + str).replace(/\D/g, '');
	// Get all numbers as an array
	const numbers = cleaned.split('');
	if (numbers.length > 0) {
		// Build pattern
		return (plus ? '+' : '') + numbers.slice(0, 3).join('') + ' ' + numbers.slice(3, 6).join('') + ' ' + numbers.slice(6, 9).join('') + ' ' + numbers.slice(9, 12).join('');
	}
	return null
}

/**
 * Check if the email is valid
 *
 * @param {string} email
 * @returns {boolean}
 */
export function validateEmail(email) {
	const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	return re.test(String(email).toLowerCase());
}

/**
 * Get the whole URL to given request
 *
 * @param {string[]} request
 *
 * @returns {string}
 */
export function getLink(request) {
	if (prettyUrl) {
		return window.location.origin + '/' + request.join('/');
	} else {
		let query = {
			lang: document.documentElement.lang
		};
		let i = 0;
		request.forEach(page => {
			if (page === '') {
				return;
			}
			query[`p[${i}]`] = page;
			i++;
		});
		const params = new URLSearchParams(query);
		return window.location.origin + "?" + params.toString();
	}
}

/**
 * Setup select elements that have additional description
 *
 * @param {Element} input
 */
export function selectInputDescriptionSetup(input) {
	const id = input.id;
	const descriptionElement = document.querySelectorAll(`.select-description[data-target="#${id}"]`);
	const update = () => {
		const val = input.value;
		const description = input.querySelector(`option[value="${val}"]`).dataset.description;
		descriptionElement.forEach(elem => {
			elem.innerHTML = description;
		});
	};
	if (descriptionElement) {
		update();
		input.addEventListener("change", update);
	}
}

export function initTooltips(dom) {
	const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="tooltip"]'))
	const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new Tooltip(tooltipTriggerEl)
	});
}

export function initAutoSaveForm() {
	// Autosave form
	document.querySelectorAll('form.autosave').forEach(form => {
		const method = form.method;
		const url = form.action;

		let lastData = new FormData(form);
		let autosaving = 0;
		const lastSave = document.querySelectorAll(`.last-save[data-target="#${form.id}"]`);
		const saveButtons = form.querySelectorAll(`[data-action="autosave"]`);
		const save = (smallLoader = true) => {
			let newData = new FormData(form);
			let changed = false;
			if (!smallLoader) {
				startLoading(false);
			}
			newData.forEach((value, key) => {
				if (changed || key === "_csrf_token" || key === 'action') {
					return;
				}
				if (!lastData.has(key)) {
					console.log("Changed - new key", key, value)
					changed = true;
				} else if (value instanceof File) {
					if (value.name !== lastData.get(key).name) {
						console.log("Changed - new file", key, value)
						changed = true;
					}
				} else if (JSON.stringify(lastData.getAll(key)) !== JSON.stringify(newData.getAll(key))) {
					console.log("Changed - new value", key, value)
					changed = true;
				}
			});
			if (!changed) {
				lastData.forEach((value, key) => {
					if (changed || key === "_csrf_token" || key === 'action') {
						return;
					}
					if (!newData.has(key)) {
						console.log("Changed - removed key", key, value)
						changed = true;
					}
				});
			}
			if (changed && autosaving === 0) {
				autosaving++;
				lastData = newData;
				newData.append("action", "autosave");
				if (smallLoader) startLoading(smallLoader);
				saveButtons.forEach(button => {
					button.disabled = true;
				});
				axios(
					{
						method,
						url,
						data: newData
					}
				)
					.then((result) => {
						autosaving--;
						stopLoading(result.data.success, smallLoader);
						saveButtons.forEach(button => {
							button.disabled = false;
						});
						lastSave.forEach(save => {
							save.innerHTML = (new Date()).toLocaleTimeString();
						});
					})
					.catch(err => {
						console.error(err);
						autosaving--;
						stopLoading(false, smallLoader);
						saveButtons.forEach(button => {
							button.disabled = false;
						});
					});
			} else if (!smallLoader) {
				stopLoading(true, false);
				lastSave.forEach(save => {
					save.innerHTML = (new Date()).toLocaleTimeString();
				});
			}
		};

		form.addEventListener("autosave", save);

		saveButtons.forEach(button => {
			button.addEventListener("click", e => {
				if (button.dataset.prevent) {
					e.preventDefault();
				}
				save(false);
			});
		})

		setInterval(save, 10000);
	});
}

let timerInterval = null;

/**
 * Initialize a timer displaying the remaining game time
 */
export function gameTimer() {
	clearInterval(timerInterval);
	const time = document.querySelector('.time');
	if (!time) {
		return;
	}

	let offset = 0;
	const serverTime = parseInt(time.dataset.servertime);
	console.log(time.dataset.servertime, serverTime);
	offset = (Date.now() / 1000) - (isNaN(serverTime) ? 0 : serverTime);
	let start = parseInt(time.dataset.start);
	let length = parseInt(time.dataset.length);
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

	// Auto-reload timer on game started
	EventServerInstance.addEventListener('game-started', loadGameInfo);

	startTimer();

	function startTimer() {
		console.log('Starting timer...', endDate, offset);
		timerInterval = setInterval(() => {
			const remaining = endDate - (Date.now() / 1000) + offset;
			if (remaining < 0) {
				time.innerHTML = "00:00";
				return;
			}
			const minutes = Math.floor(remaining / 60).toString().padStart(2, '0');
			const seconds = Math.floor(remaining % 60).toString().padStart(2, '0');
			time.innerHTML = `${minutes}:${seconds}`;
		}, 50);
	}

	/**
	 * Set the timers to the current game status
	 */
	function loadGameInfo() {
		axios.get('/api/game/loaded')
			.then(response => {
				/**
				 * @type {{started:boolean,finished:boolean,currentServerTime:number,startTime:number|null,gameLength:number,loadTime:number,playerCount:number,teamCount:number,mode:object}}
				 */
				const data = response.data;
				if (data.currentServerTime) {
					time.dataset.servertime = data.currentServerTime.toString();
				}
				if (data.started && !data.finished && data.startTime) {
					time.dataset.start = data.startTime.toString();
					time.dataset.length = data.gameLength.toString();
				} else {
					time.dataset.start = '0';
					time.dataset.length = '0';
				}
				setTimes();
			});
	}

	function setTimes() {
		const parent = time.parentElement;
		const serverTime = parseInt(time.dataset.servertime);
		offset = (Date.now() / 1000) - (isNaN(serverTime) ? 0 : serverTime);
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