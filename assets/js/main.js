import * as bootstrap from 'bootstrap'
import {formatPhoneNumber} from './functions.js';
import axios from 'axios';
import flatpickr from "flatpickr";

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';

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
		let value = '', wrap = input.classList.contains("date-picker"), enable = undefined;
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		if (input.dataset.events) {
			const events = JSON.parse(input.dataset.events);
			enable = Object.keys(events);
		}
		flatpickr(input, {
			//defaultDate: value,
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentNode,
			wrap,
			enable,
		});
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
	// Tooltips
	const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'))
	const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new bootstrap.Tooltip(tooltipTriggerEl)
	});

	// Toggles
	document.querySelectorAll('[data-toggle="submit"]').forEach(element => {
		element.addEventListener("change", () => {
			element.findParentElement("form").submit();
		});
	});
});

