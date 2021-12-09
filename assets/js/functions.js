import {Tooltip} from "bootstrap";

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
		return window.location.origin + '/' + document.documentElement.lang + '/' + request.join('/');
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