import {initPopover} from '../../includes/popovers';
import {startLoading, stopLoading} from '../../loaders';
import {getTopPlayers, LigaPlayer, registerPlayer} from '../../api/endpoints/players';
import {triggerNotificationError} from '../../includes/notifications';
import {ErrorResponse, ResponseError} from '../../includes/apiClient';
import {Modal} from 'bootstrap';

export default function initLaserLiga() : void {
	initPopover();

	const registerModalElement = document.getElementById('register') as HTMLDivElement;
	const registerModal = Modal.getOrCreateInstance(registerModalElement);

	const playerModalElement = document.getElementById('player-profile') as HTMLDivElement;
	const playerModal = Modal.getOrCreateInstance(playerModalElement);

	const registerForm = document.getElementById('liga-register-form') as HTMLFormElement;
	if (registerForm) {
		const nameInput = registerForm.querySelector<HTMLInputElement>('#loginName');
		const emailInput = registerForm.querySelector<HTMLInputElement>('#loginEmail');
		const passwordInput = registerForm.querySelector<HTMLInputElement>('#loginPassword');
		registerForm.addEventListener('submit', (e) => {
			e.preventDefault();
			startLoading();
			registerPlayer({
				name: nameInput.value,
				email: emailInput.value,
				password: passwordInput.value,
			})
				.then(player => {
					stopLoading(true);
					registerModal.hide();
					registerForm.reset();
					showPlayerProfile(player);
				})
				.catch(async error => {
					await triggerNotificationError(error);
					if (error instanceof ResponseError) {
						const data = await error.data as ErrorResponse | object;
						if ('values' in data && typeof data.values === 'object') {
							if ('name' in data.values) {
								nameInput.setCustomValidity(data.values.name);
							}
							if ('email' in data.values) {
								emailInput.setCustomValidity(data.values.email);
							}
							if ('password' in data.values) {
								passwordInput.setCustomValidity(data.values.password);
							}
						}
					}
				})
		})
	}

	const topPlayers = document.getElementById('top-players') as HTMLDivElement;
	if (topPlayers) {
		getTopPlayers()
			.then(players => {
				let i = 1;
				for (const player of players) {
					const playerWrapper = document.createElement('div');
					playerWrapper.classList.add('card');
					playerWrapper.innerHTML = `<div class="card-body text-center"><img src="https://laserliga.cz/user/${player.code}/avatar" alt="${player.nickname}" class="avatar" style="max-height: 5rem;" /><h3 class="card-title">${i}. <strong class="text-primary">${player.nickname}</strong></h3><h4>${player.code}</h4><img src="https://laserliga.cz/user/${player.code}/title/svg" alt="title" class="title" style="max-height: 1.5rem;"><p class="my-2">${player.rank.toLocaleString()} <i class="fa-solid fa-star"></i></p><p><button type="button" class="btn btn-primary"><i class="fa-solid fa-eye"></i> ${topPlayers.dataset.show}</button></p>`;
					topPlayers.appendChild(playerWrapper);
					playerWrapper.addEventListener('click', () => {
						showPlayerProfile(player);
					});
					i++;
				}
			})
			.catch(error => {
				triggerNotificationError(error);
			})
	}

	function showPlayerProfile(player : LigaPlayer) {
		const title = playerModalElement.querySelector<HTMLHeadingElement>('.modal-title');
		if (title) {
			title.innerText = player.nickname;
		}

		const iframe = playerModalElement.querySelector<HTMLIFrameElement>('iframe');
		if (iframe) {
			iframe.src = 'https://laserliga.cz/user/' + player.code + '?iframe=1';
		}

		playerModal.show();
	}
}