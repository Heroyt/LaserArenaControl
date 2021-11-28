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
	document.querySelectorAll('input[type="date"]').forEach(input => {
		flatpickr(input, {
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentNode,
		});
	});
	document.querySelectorAll('input[type="datetime"]').forEach(input => {
		flatpickr(input, {
			dateFormat: "d.m.Y H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			time_24hr: true,
			appendTo: input.parentNode
		});
	});
	document.querySelectorAll('input[type="time"]').forEach(input => {
		flatpickr(input, {
			dateFormat: "H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			noCalendar: true,
			time_24hr: true,
			appendTo: input.parentNode,
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
	})
});

