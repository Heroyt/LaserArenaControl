import {initTooltips} from "../../functions";
import * as jscolor from "@eastdesire/jscolor";
import flatpickr from "flatpickr";

export default function initPrintSettings() {

	document.querySelectorAll('.print-style').forEach(dom => {
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
				.firstElementChild;
			addStyle.dataset.key = (parseInt(addStyle.dataset.key) + 1).toString();
			styleWrapper.appendChild(styleDom);
			jscolor.install();
			initTooltips(styleDom);
			initStyle(styleDom);
		});
	}


	document.querySelectorAll('.print-style-date').forEach(dom => {
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
				.firstElementChild;
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
function initStyle(dom) {
	const rmBtn = dom.querySelector('.remove');
	if (rmBtn) {
		rmBtn.addEventListener('click', () => {
			dom.remove();
		});
	}
	const img = dom.querySelector('img');
	const imgInput = dom.querySelector('input[type="file"]');
	imgInput.addEventListener('change', () => {
		const files = imgInput.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener("load", function () {
				img.style.display = 'initial';
				img.src = this.result;
			});
		}
	});
}

/**
 *
 * @param dom {Element}
 */
function initStyleDate(dom) {
	dom.querySelectorAll('.date').forEach(input => {
		let values = input.querySelector("[data-input]").value.split('-');
		let options = {
			defaultDate: values,
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentNode,
			wrap: true,
			mode: 'range',
			conjunction: '-'
		};
		flatpickr(input, options);
	});
	const rmBtn = dom.querySelector('.remove');
	if (rmBtn) {
		rmBtn.addEventListener('click', () => {
			dom.remove();
		});
	}
}