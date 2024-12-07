import {startLoading, stopLoading} from "../../loaders";
import {fetchPost} from "../../includes/apiClient";
import {triggerNotificationError} from '../../includes/notifications';

export default function initCacheSettings() {

	const btns = document.querySelectorAll('.btn-clear-cache') as NodeListOf<HTMLButtonElement>;

	btns.forEach(btn => {
		if (!btn.dataset.href) {
			return;
		}
		const href = btn.dataset.href;
		btn.addEventListener('click', () => {
			startLoading();
            fetchPost(href)
				.then(() => {
					stopLoading();
				})
				.catch(e => {
					triggerNotificationError(e);
					stopLoading(false);
				})
		});
	});
}