import {Modal} from 'bootstrap';
import Player from '../../game/player';
import {UserSearchData} from '../../interfaces/userSearchData';
import {initUserAutocomplete} from '../../components/userPlayerSearch';
import Game from '../../game/game';
import {findUsers, findUsersPublic} from '../../api/endpoints/userSearch';

export default class UserSearch {

	foundPlayers: { [index: string]: UserSearchData } = {};
	userSearchModalElem: HTMLDivElement;
	userSearchModal: Modal;
	userSearchInput: HTMLInputElement;
	userSearchResults: HTMLDivElement;
	userSearchLoader: HTMLDivElement;

	searchedPlayer: Player | null = null;
	private hideMails: boolean;

	constructor(hideMails: boolean = false) {
		this.hideMails = hideMails;
		this.userSearchModalElem = document.getElementById('userSearchModal') as HTMLDivElement;
		this.userSearchModal = new Modal(this.userSearchModalElem);
		this.userSearchInput = this.userSearchModalElem.querySelector('#user-search') as HTMLInputElement;
		this.userSearchResults = this.userSearchModalElem.querySelector('#search-results') as HTMLDivElement;

		this.userSearchLoader = document.createElement('div');
		this.userSearchLoader.classList.add('list-group-item');
		this.userSearchLoader.innerHTML = `<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>`;
	}

	init() {
		this.userSearchModalElem.addEventListener('hide.bs.modal', () => {
			this.searchedPlayer = null;
		});

		/** Debounce timeout */
		let userSearchTimeout: NodeJS.Timeout;
		this.userSearchInput.addEventListener('input', () => {
			clearTimeout(userSearchTimeout);
			this.userSearchResults.innerHTML = '';
			userSearchTimeout = setTimeout(() => {
				this.findPlayers();
			}, 500);
		});
	}

	findPlayers() {
		this.foundPlayers = {};
		this.userSearchResults.appendChild(this.userSearchLoader);
		let finishedLocal = false;
		let finishedPublic = false;
		findUsers(this.userSearchInput.value)
			.then(results => {
				results.forEach(data => {
					this.createUserSearchResult(data);
				});
				if (finishedPublic) {
					this.userSearchLoader.remove();
				}
			})
			.finally(() => {
				finishedLocal = true;
			});
		findUsersPublic(this.userSearchInput.value)
			.then(results => {
				results.forEach(data => {
					this.createUserSearchResult(data);
				});
				if (finishedLocal) {
					this.userSearchLoader.remove();
				}
			})
			.finally(() => {
				finishedPublic = true;
			});
	}

	initGame(game: Game) {
		game.players.forEach(player => {
			player.row.addEventListener('user-search', (e: CustomEvent<Player>) => {
				this.searchedPlayer = e.detail;
				this.userSearchInput.value = e.detail.$name.value;
				this.userSearchModal.show();
				if (this.userSearchInput.value !== '') {
					this.findPlayers();
				}
			});

			initUserAutocomplete(player.$name, (name, code, rank) => {
				player.name = name;
				player.$name.value = name;
				player.realSkill = rank;
				player.setUserCode(code);
				player.update();
				player.game.reassignPlayerSkills();
			});
		});
	}

	createUserSearchResult(playerData: UserSearchData): void {
		if (this.foundPlayers[playerData.code]) {
			return;
		}
		this.foundPlayers[playerData.code] = playerData;

		const elem = document.createElement('a');
		elem.classList.add('list-group-item', 'list-group-item-action');
		elem.dataset.code = playerData.code;
		elem.setAttribute('data-code', playerData.code);
		elem.innerText = playerData.code + ': ' + playerData.nickname;
		if (!this.hideMails) {
			elem.innerText += ` (${playerData.email})`;
		}

		this.userSearchResults.insertBefore(elem, this.userSearchLoader);

		elem.addEventListener('click', () => {
			if (this.searchedPlayer) {
				this.searchedPlayer.$name.value = playerData.nickname;
				this.searchedPlayer.name = playerData.nickname;
				this.searchedPlayer.realSkill = playerData.rank;
				this.searchedPlayer.setUserCode(playerData.code);
				this.searchedPlayer.update();
				this.searchedPlayer.game.reassignPlayerSkills();
			}
			this.userSearchModal.hide();
		});
	}

}