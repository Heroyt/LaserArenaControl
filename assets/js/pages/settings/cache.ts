import axios from "axios";
import {startLoading, stopLoading} from "../../loaders";

export default function initCacheSettings() {

	const btns = document.querySelectorAll('.btn-clear-cache') as NodeListOf<HTMLButtonElement>;

	btns.forEach(btn => {
		if (!btn.dataset.href) {
			return;
		}
		const href = btn.dataset.href;
		btn.addEventListener('click', () => {
			startLoading();
			axios.post(href, {})
				.then(() => {
					stopLoading();
				})
				.catch(() => {
					stopLoading(false);
				})
		});
	});
}