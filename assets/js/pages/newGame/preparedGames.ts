import {Offcanvas} from 'bootstrap';
import {startLoading, stopLoading} from '../../loaders';
import {GameData} from '../../interfaces/gameInterfaces';
import Game from '../../game/game';
import {
	deleteAllPreparedGames,
	deletePreparedGame,
	getPreparedGames,
	PreparedGameData,
	sendPreparedGame,
} from '../../api/endpoints/preparedGames';
import {initTooltips} from '../../includes/tooltips';

const borderColors = {
	'prepared': 'gray',
	'user-local': 'primary',
	'user-public': 'info',
}

export default class NewGamesPrepared {

	game: Game;
	gamesPreparedWrapper: HTMLDivElement;
	saveBtn: HTMLButtonElement;
	private offcanvas: Offcanvas;

	constructor(game: Game, gamesPreparedWrapper: HTMLDivElement, saveBtn: HTMLButtonElement) {
		this.game = game;
		this.gamesPreparedWrapper = gamesPreparedWrapper;
		this.saveBtn = saveBtn;
		this.offcanvas = Offcanvas.getOrCreateInstance(this.gamesPreparedWrapper.parentElement);

		document.getElementById('deleteAllPreparedGames').addEventListener('click', () => {
			startLoading();
			deleteAllPreparedGames()
				.then(() => {
					stopLoading(true);
					this.updatePreparedGames();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});

		this.saveBtn.addEventListener('click', () => {
			const data = this.game.export();

			startLoading();
			sendPreparedGame(data)
				.then(() => {
					stopLoading(true);
					this.updatePreparedGames();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});

		document.getElementById('preparedGames').addEventListener('show.bs.offcanvas', () => {
			this.updatePreparedGames();
		});
	}

	initPreparedGame(preparedGameWrapper: HTMLDivElement): void {
		initTooltips(preparedGameWrapper);
		const id = parseInt(preparedGameWrapper.dataset.id);
		const data: GameData = JSON.parse(preparedGameWrapper.dataset.game);
		const loadBtn = preparedGameWrapper.querySelector('.load') as HTMLButtonElement;
		const deleteBtn = preparedGameWrapper.querySelector('.delete') as HTMLButtonElement;

		loadBtn.addEventListener('click', () => {
			this.game.import(data);
			this.offcanvas.hide();
		});

		deleteBtn.addEventListener('click', () => {
			startLoading();
			deletePreparedGame(id)
				.then(() => {
					stopLoading(true);
					preparedGameWrapper.remove();
				})
				.catch(e => {
					stopLoading(false);
					console.error(e);
				});
		});
	}

	updatePreparedGames() {
		getPreparedGames()
			.then(response => {
				this.gamesPreparedWrapper.innerHTML = '';
				response.forEach(groupData => {
					this.addPreparedGame(groupData);
				});
			});
	}


	addPreparedGame(preparedGameData: PreparedGameData) {

		// Find an existing prepared game wrapper
		let preparedGameWrapper = this.gamesPreparedWrapper.querySelector(`.prepared-game[data-id="${preparedGameData.id_game}"]`) as HTMLDivElement;
		if (!preparedGameWrapper) {
			const tmp = document.createElement('div');
			let playersHTML = '';
			Object.values(preparedGameData.data.players).forEach(player => {
				playersHTML += `<span class="badge m-1 text-bg-team-${system}-${player.teamNum}">${player.name}</span>`;
			});
			tmp.innerHTML = `<div class="prepared-game card mb-4 border-4 border-${borderColors[preparedGameData.type]}" data-id="${preparedGameData.id_game}">` +
				`<div class="card-body">` +
				`<div class="input-group w-100">` +
				`<span class="game-mode flex-grow-1 input-group-text">${preparedGameData.data.mode.name}</span>` +
				`<button type="button" data-toggle="tooltip" title="${messages.load}" class="btn btn-success load"><i class="fa-solid fa-upload"></i></button>` +
				`<button type="button" data-toggle="tooltip" title="${messages.delete}" class="btn btn-danger delete"><i class="fa-solid fa-trash"></i></button>` +
				`</div>` +
				`<div class="players mt-2">${playersHTML}</div>` +
				`</div>` +
				`</div>`;
			preparedGameWrapper = tmp.firstElementChild as HTMLDivElement;
			preparedGameWrapper.dataset.game = JSON.stringify(preparedGameData.data);
			this.gamesPreparedWrapper.appendChild(preparedGameWrapper);
			this.initPreparedGame(preparedGameWrapper);
		}
	}
}