import flatpickr from "flatpickr";
import {Options} from "flatpickr/dist/types/options.js";

/**
 *
 * @param elem {HTMLElement|Document|null}
 */
export default function initDatePickers(elem: HTMLElement | HTMLDocument = null) {
	if (!elem) {
		elem = document;
	}

    import(`flatpickr/dist/l10n/cs`).then(localizationModule => {
        const lang = localizationModule.default.cs;
		flatpickr.localize(lang);

		(elem.querySelectorAll('input[type="date"]:not([data-input]), .date-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
			let value = '', wrap = !(input instanceof HTMLInputElement);
			if (wrap) {
				value = (input.querySelector("[data-input]") as HTMLInputElement).value;
			} else if (input instanceof HTMLInputElement) {
				value = input.value;
			}
			let options: Options = {
				defaultDate: value,
				dateFormat: "d.m.Y",
				position: "auto center",
				positionElement: input,
				static: true,
				appendTo: input.parentElement,
				wrap,
			};
			if (input.dataset.events) {
				const events = JSON.parse(input.dataset.events);
				options.enable = Object.keys(events);
			}
			flatpickr(input, options);
		});
		(elem.querySelectorAll('input[type="datetime"]:not([data-input]), .datetime-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
			let value = '', wrap = !(input instanceof HTMLInputElement);
			if (wrap) {
				value = (input.querySelector("[data-input]") as HTMLInputElement).value;
			} else if (input instanceof HTMLInputElement) {
				value = input.value;
			}
			flatpickr(input, {
				defaultDate: value,
				dateFormat: "d.m.Y H:i",
				position: "auto center",
				positionElement: input,
				enableTime: true,
				time_24hr: true,
				appendTo: input.parentElement,
				wrap: wrap,
			});
		});
		(elem.querySelectorAll('input[type="time"]:not([data-input]), .time-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
			let value = '', wrap = !(input instanceof HTMLInputElement);
			if (wrap) {
				value = (input.querySelector("[data-input]") as HTMLInputElement).value;
			} else if (input instanceof HTMLInputElement) {
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
				appendTo: input.parentElement,
				wrap: wrap,
				onOpen: (e) => {
					(elem.querySelectorAll('.numInput') as NodeListOf<HTMLInputElement>).forEach((pickerInput) => {
						pickerInput.name = "flatpickr[]";
						pickerInput.type = "number";
					});
				},
				onClose: (e) => {
					(elem.querySelectorAll('.numInput') as NodeListOf<HTMLInputElement>).forEach((pickerInput) => {
						pickerInput.type = "text";
					});
				}
			});
		});
	});
}