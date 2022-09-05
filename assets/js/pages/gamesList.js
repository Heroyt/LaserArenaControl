import axios from "axios";
import {startLoading, stopLoading} from "../loaders";

export default function initGamesList() {
	const reImportBtns = document.querySelectorAll('.re-import');
	reImportBtns.forEach(btn => {
		const gameCode = btn.dataset.code;
		if (!gameCode || gameCode === '') {
			btn.remove();
			return;
		}
		btn.addEventListener('click', () => {
			startLoading();
			axios.post('/api/results/import/' + gameCode, {})
				.then(response => {
					stopLoading(true);
					window.location.reload();
				})
				.catch(() => {
					stopLoading(false);
				});
		});
	});
}