import Game from "../game/game";
import axios from "axios";
import {lang} from "../functions";
import EventServerInstance from "../EventServer";
import {startLoading, stopLoading} from "../loaders";

export default function initNewGamePage() {
	/**
	 * @type {HTMLFormElement}
	 */
	const form = document.getElementById('new-game-content');

	// Send form via ajax
	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(form);
		console.log(data);

		if (!validateForm(data)) {
			return;
		}

		startLoading();
		axios.post('/', data)
			.then(response => {
				stopLoading();
			})
			.catch(response => {
				stopLoading(false);
			});
	});

	// Autosave to local storage
	form.addEventListener('update', () => {
		const data = game.export();
		console.log('saving data', data);
		localStorage.setItem('new-game-data', JSON.stringify(data));
	});

	const game = new Game();

	const localData = localStorage.getItem('new-game-data');
	if (gameData) {
		game.import(gameData);
	} else if (localData) {
		game.import(JSON.parse(localData));
	}

	/**
	 * @type {HTMLSelectElement}
	 */
	const lastGamesSelect = document.getElementById('last-games');

	lastGamesSelect.addEventListener('change', () => {
		const option = lastGamesSelect.querySelector(`option[value="${lastGamesSelect.value}"]`);
		if (!option) {
			return;
		}
		game.import(JSON.parse(option.dataset.game));
	});

	document.addEventListener('clear-all', () => {
		lastGamesSelect.value = '';
	});

	loadLastGames();

	EventServerInstance.addEventListener('game-imported', loadLastGames);

	function loadLastGames() {
		axios.get('/api/games', {
			params: {
				limit: 10,
				orderBy: 'start',
				desc: true,
				excludeFinished: true,
				expand: true,
			},
		})
			.then((response) => {
				response.data.forEach(
					/**
					 * @param game {GameData}
					 */
					game => {
						const test = lastGamesSelect.querySelector(`option[value="${game.code}"]`);
						if (test) {
							return; // Do not add duplicates
						}

						const gameDate = new Date(game.start.date.replace(' ', 'T'));

						const option = document.createElement('option');
						option.value = game.code;
						option.dataset.game = JSON.stringify(game);

						const teamCount = Object.keys(game.teams).length;

						if (lastGamesSelect.querySelectorAll('option[data-game]').length >= 10) {
							lastGamesSelect.querySelector('option[data-game]').remove();
						}

						lastGamesSelect.appendChild(option);

						const players = Object.values(game.players).map(player => {
							return player.name;
						}).join(', ');

						option.innerText = `${game.fileNumber} - [${gameDate.getHours()}:${gameDate.getMinutes()}] ${players}`;

						Promise.all([
							lang('%d player', '%d players', game.playerCount, 'game'),
							lang('%d team', '%d teams', teamCount, 'game'),
							lang(game.mode.name, null, 1, 'gameModes')
						])
							.then(values => {
								const playerString = values[0].data.replace('%d', game.playerCount.toString());
								const teamString = game.mode.type === 'TEAM' ? values[1].data.replace('%d', teamCount) + ', ' : '';
								option.innerText = `${game.fileNumber} - [${gameDate.getHours()}:${gameDate.getMinutes()}] ${values[2].data}: ${playerString}, ${teamString} ${players}`;
							})
					}
				);
			})
			.catch(response => {

			})
	}

	/**
	 * @param data {FormData}
	 * @return boolean
	 */
	function validateForm(data) {
		if (data.get('action') !== 'load') {
			return true;
		}

		const activePlayers = game.getActivePlayers();
		if (activePlayers.length < 2) {
			game.noPlayersTooltip.show();
			return false;
		}

		if (game.getModeType() === 'TEAM') {
			let ok = true;
			activePlayers.forEach(player => {
				if (player.team === null) {
					ok = false;
					player.selectTeamTooltip.show();
				}
			});
			if (!ok) {
				return false;
			}
		}

		return true;
	}
}

/**
 * @typedef {date: String, timezone_type: Number, timezone: String} PhpDateTime
 */
/**
 * @typedef {
 * 			id: Number,
 * 			name: String,
 * 			score: Number,
 * 			skill: Number,
 * 			vest: Number,
 * 			position: Number,
 * 			accuracy: Number,
 * 			hits: Number,
 * 			deaths: Number,
 * 			shots: Number,
 * 			teamNum: Number,
 * 			color: Number
 * 		} PlayerData
 */
/**
 * @typedef {
 * 			id: Number,
 * 			name: String,
 * 			score: Number,
 * 			color: Number,
 * 			playerCount: Number,
 * 			position: Number
 * 		} TeamData
 */
/**
 * @typedef {
 * 	{
 * 		id: Number,
 * 		code: String,
 * 		fileNumber: Number|String,
 * 		playerCount: Number,
 * 		fileTime: PhpDateTime,
 * 		start: PhpDateTime,
 * 		end: PhpDateTime,
 * 		mode: {id: Number, name: String, description: String, type: 'TEAM'|'SOLO'},
 * 		players: Object.<string, PlayerData>,
 * 		teams: Object.<string, TeamData>,
 * 	}
 * 	} GameData
 */