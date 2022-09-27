import axios from "axios";
import {startLoading, stopLoading} from "../loaders";

export default function initGamesList() {
	import(/* webpackChunkName: "datePickers" */ '../datePickers').then(module => {
		// Init date pickers
		module.default()

		const reImportBtns = document.querySelectorAll('.re-import') as NodeListOf<HTMLButtonElement>;
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

		const syncBtns = document.querySelectorAll('.liga-sync') as NodeListOf<HTMLButtonElement>;
		syncBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			if (!gameCode || gameCode === '') {
				btn.remove();
				return;
			}
			btn.addEventListener('click', () => {
				startLoading();
				axios.post(`/api/games/${gameCode}/sync`, {})
					.then(response => {
						stopLoading(true);
					})
					.catch(() => {
						stopLoading(false);
					});
			});
		});
		const recalcPointsBtns = document.querySelectorAll('.recalc-skill') as NodeListOf<HTMLButtonElement>;
		recalcPointsBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			if (!gameCode || gameCode === '') {
				btn.remove();
				return;
			}
			btn.addEventListener('click', () => {
				startLoading();
				axios.post('/api/game/' + gameCode + '/recalcSkill', {})
					.then(response => {
						stopLoading(true);
					})
					.catch(() => {
						stopLoading(false);
					});
			});
		});
		const soloSwitchBtns = document.querySelectorAll('.solo-switch') as NodeListOf<HTMLButtonElement>;
		soloSwitchBtns.forEach(btn => {
			const gameCode = btn.dataset.code;
			const mode = btn.dataset.mode;
			if (!gameCode || gameCode === '') {
				btn.remove();
				return;
			}
			btn.addEventListener('click', () => {
				startLoading();
				axios.post('/api/game/' + gameCode + '/changeMode', {
					mode
				})
					.then(response => {
						stopLoading(true);
						location.reload();
					})
					.catch(() => {
						stopLoading(false);
					});
			});
		});
	});
}