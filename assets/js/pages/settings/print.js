import {initTooltips} from "../../functions";

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