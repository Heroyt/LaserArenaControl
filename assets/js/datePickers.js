import flatpickr from "flatpickr";

/**
 *
 * @param elem {HTMLElement|Document|null}
 */
export default function initDatePickers(elem = null) {
	if (!elem) {
		elem = document;
	}

	import(`/node_modules/flatpickr/dist/l10n/${activeLanguageCode}.js`).then(localizationModule => {
		const lang = localizationModule.default[activeLanguageCode];
		flatpickr.localize(lang);

		elem.querySelectorAll('input[type="date"]:not([data-input]), .date-picker').forEach(input => {
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
		elem.querySelectorAll('input[type="datetime"]:not([data-input]), .datetime-picker').forEach(input => {
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
		elem.querySelectorAll('input[type="time"]:not([data-input]), .time-picker').forEach(input => {
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
					elem.querySelectorAll('.numInput').forEach((pickerInput) => {
						pickerInput.name = "flatpickr[]";
						pickerInput.type = "number";
					});
				},
				onClose: (e) => {
					elem.querySelectorAll('.numInput').forEach((pickerInput) => {
						pickerInput.type = "text";
					});
				}
			});
		});
	});
}