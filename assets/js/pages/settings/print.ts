import {initTooltips} from "../../functions";
// @ts-ignore
import * as jscolor from "@eastdesire/jscolor";
import flatpickr from "flatpickr";
import {Options} from "flatpickr/dist/types/options.js";

export default function initPrintSettings() {

	(document.querySelectorAll('.print-style') as NodeListOf<HTMLDivElement>).forEach(dom => {
		initStyle(dom);
	});
	const styleTemplate = document.getElementById("print-style-template").innerHTML;
	const addStyle = document.getElementById('addStyle');
	const styleWrapper = document.getElementById('styles');
	if (addStyle && styleWrapper) {
		addStyle.addEventListener('click', () => {
			const styleDom = new DOMParser()
				.parseFromString(
					styleTemplate.replaceAll('#key#', addStyle.dataset.key),
					"text/html"
				)
				.body
				.firstElementChild as HTMLDivElement;
			addStyle.dataset.key = (parseInt(addStyle.dataset.key) + 1).toString();
			styleWrapper.appendChild(styleDom);
			jscolor.install();
			initTooltips(styleDom);
			initStyle(styleDom);
		});
	}


	(document.querySelectorAll('.print-style-date') as NodeListOf<HTMLDivElement>).forEach(dom => {
		initStyleDate(dom);
	});
	const addStyleDate = document.getElementById('addRange');
	const styleDateTemplate = document.getElementById("print-style-date").innerHTML;
	const styleDateWrapper = document.getElementById('ranges');
	if (addStyleDate && styleDateWrapper && styleDateTemplate) {
		addStyleDate.addEventListener('click', () => {
			const styleDateDom = new DOMParser()
				.parseFromString(
					styleDateTemplate.replaceAll('#i#', addStyleDate.dataset.key),
					"text/html"
				)
				.body
				.firstElementChild as HTMLDivElement;
			addStyleDate.dataset.key = (parseInt(addStyleDate.dataset.key) + 1).toString();
			styleDateWrapper.appendChild(styleDateDom);
			jscolor.install();
			initTooltips(styleDateDom);
			initStyleDate(styleDateDom);
		});
	}
}


/**
 *
 * @param dom {Element}
 */
function initStyle(dom: HTMLElement): void {
	const rmBtn = dom.querySelector('.remove') as HTMLButtonElement | null;
	if (rmBtn) {
		rmBtn.addEventListener('click', () => {
			dom.remove();
		});
	}

	(dom.querySelectorAll('input,select') as NodeListOf<HTMLInputElement | HTMLSelectElement>).forEach(input => {
		const event = new Event('autosave', {bubbles: true});
		input.addEventListener('change', () => {
			input.dispatchEvent(event);
		});
	})

	const img = dom.querySelector('img.portrait') as HTMLImageElement;
	const imgLandscape = dom.querySelector('img.landscape') as HTMLImageElement;
	const imgInput = dom.querySelector('input[type="file"].portrait') as HTMLInputElement;
	const imgInputLandscape = dom.querySelector('input[type="file"].landscape') as HTMLInputElement;
	imgInput.addEventListener('change', () => {
		const files = imgInput.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener("load", function () {
				img.style.display = 'initial';
				if (this.result instanceof ArrayBuffer) {
					img.src = Buffer.from(this.result).toString('base64');
				} else {
					img.src = this.result;
				}
			});
		}
	});
	imgInputLandscape.addEventListener('change', () => {
		const files = imgInputLandscape.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener("load", function () {
				imgLandscape.style.display = 'initial';
				if (this.result instanceof ArrayBuffer) {
					img.src = Buffer.from(this.result).toString('base64');
				} else {
					img.src = this.result;
				}
			});
		}
	});
}

/**
 *
 * @param dom {Element}
 */
function initStyleDate(dom: HTMLDivElement): void {
	(dom.querySelectorAll('.date') as NodeListOf<HTMLInputElement>).forEach(input => {
		let values = (input.querySelector("[data-input]") as HTMLInputElement).value.split('-');
		let options: Options = {
			defaultDate: values,
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentElement,
			wrap: true,
			mode: 'range',
			conjunction: '-'
		};
		flatpickr(input, options);
	});

	dom.querySelectorAll('input,select').forEach(input => {
		const event = new Event('autosave', {bubbles: true});
		input.addEventListener('change', () => {
			input.dispatchEvent(event);
		});
	})

	const rmBtn = dom.querySelector('.remove');
	if (rmBtn) {
		rmBtn.addEventListener('click', () => {
			dom.remove();
		});
	}
}