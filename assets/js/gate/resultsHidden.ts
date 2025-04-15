import GateScreen from './gateScreen';
import {shuffle} from '../includes/functions';
import {PlayerData, TeamData} from '../components/gate/types';
import {initPlayer, reorderPlayers, reorderTeams, updateAccuracySVG} from '../components/gate/animateResults';

const gameResultsExp = /results-game-(\d+)/;

export default class ResultsHiddenScreen implements GateScreen {
	content: HTMLDivElement;
	private animationStop: boolean = false;
	private minScore: number = 99999;
	private maxScore: number = 0;
	private removePreviousContent: () => void;

	init(content: HTMLDivElement, removePreviousContent: () => void): void {
		this.content = content;
		this.removePreviousContent = removePreviousContent;
	}

	animateIn(): void {
		this.content.classList.add('content', 'in');

		setTimeout(() => {
			this.removePreviousContent();
			this.content.classList.remove('in');
		}, 2000);

		this.startAnimation();
	}

	animateOut(): void {
		this.animationStop = true;
		this.content.classList.add('out');
	}

	isSame(active: GateScreen): boolean {
		if (!(active instanceof ResultsHiddenScreen)) {
			return false;
		}
		const matchNew = this.content.className.match(gameResultsExp);
		const matchActive = active.content.className.match(gameResultsExp);
		return matchNew !== null && matchActive !== null && (matchNew[1] ?? '') === (matchActive[1] ?? '');
	}

	showTimer(): boolean {
		return false;
	}

	clear() {
		this.content = undefined;
	}

	private startAnimation() {
		const teamsWrapper: HTMLDivElement = this.content.querySelector('section.teams');
		const playersWrapper: HTMLDivElement = this.content.querySelector('section.players');
		const players: NodeListOf<HTMLDivElement> = this.content.querySelectorAll('.player');
		const teams: NodeListOf<HTMLDivElement> = this.content.querySelectorAll('.team');

		// Randomly shuffle initial position of players and teams
		const playersArray: HTMLDivElement[] = shuffle(Array.from(players));
		const teamsArray: HTMLDivElement[] = shuffle(Array.from(teams));

		const playersData: PlayerData[] = [];
		const teamsData: Map<string, TeamData> = new Map();

		this.minScore = 99999;
		this.maxScore = 0;

		// Find min and max score for players
		players.forEach(player => {
			const score = parseInt(player.dataset.score);
			if (score > this.maxScore) {
				this.maxScore = score;
			}
			if (score < this.minScore) {
				this.minScore = score;
			}
		});

		// Initialize teams - save team data and reset the score
		teamsArray.forEach((team, key) => {
			const scoreEl = team.querySelector('.score') as HTMLDivElement;
			const scoreValueEl = scoreEl.querySelector('.value') as HTMLSpanElement;
			scoreValueEl.innerText = '0';
			team.classList.add('animating');
			team.style.order = key.toString();
			teamsData.set(team.dataset.team, {
				team, scoreEl, scoreValueEl, score: parseInt(scoreEl.dataset.score), currentScore: 0,
			});
		});

		// Initialize players - prepare animation and parse all information
		playersArray.forEach((player, key) => {
			const playerData = initPlayer(player, key, this.minScore, this.maxScore);

			// Save player data
			playersData.push(playerData);
		});

		// Timeout to let the initial animation finish
		setTimeout(() => {
			this.animate(playersData, teamsData, playersWrapper, teamsWrapper);
		}, 1000);
	}

	private animate(playersData: PlayerData[], teamsData: Map<string, TeamData>, playersWrapper: HTMLDivElement, teamsWrapper: HTMLDivElement) {
		let now = Date.now();
		const playerCount: number = playersData.length;
		/**
		 * @type {number} How many milliseconds has passed since the animation start
		 */
		let counter: number = 0;
		/**
		 * @type {number} Milliseconds until the next reorder. We don't need to reorder after every update
		 */
		let sortCounter: number = 200;

		// Finish the player animation-in
		playersData.forEach((playerData, key) => {
			playerData.player.style.animationDelay = null;
			playerData.player.classList.add('animating');
			playerData.player.classList.remove('animate-in');
		});

		// Rewrite the default flex display to allow position switching
		if (teamsWrapper) {
			teamsWrapper.style.display = 'block';
		}

		/**
		 * The main animation function - runs 1 step of the animation using a set time increment
		 * @param increment Set time increment in milliseconds
		 */
		const draw = (increment: number) => {
			// Calculate the real increment until last draw
			const realIncrement = Date.now().valueOf() - now.valueOf();
			counter += realIncrement;
			sortCounter -= realIncrement;
			now = Date.now();

			if (this.animationStop) {
				return;
			}

			// Reset team's current (animated) score
			teamsData.forEach(team => {
				team.currentScore = 0;
			});

			// Increment each player
			playersData.forEach(playerData => {
				// Add a random realistic value to a player
				// The "realistic" value is achieved by limittin the score between min and max scores
				// It the current score approaches the min score, the probability to increase the score should be higher and vice versa
				playerData.currentScore += this.randomIncrement(playerData.currentScore, this.minScore, this.maxScore);
				playerData.scoreValueEl.innerText = Math.round(playerData.currentScore).toLocaleString();

				// Add player's score to its team
				if (teamsData.has(playerData.team)) {
					teamsData.get(playerData.team).currentScore += playerData.currentScore;
				}

				// Update lives if necessary
				if (playerData.lives) {
					playerData.lives.current += this.randomIncrement(playerData.lives.current, 0, playerData.lives.start);
					playerData.lives.current = Math.min(playerData.lives.start, Math.max(0, playerData.lives.current));
					playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
				}

				// Update ammo if necessary
				if (playerData.ammo) {
					playerData.ammo.current += this.randomIncrement(playerData.ammo.current, 0, playerData.ammo.start);
					playerData.ammo.current = Math.min(playerData.ammo.start, Math.max(0, playerData.ammo.current));
					playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
				}

				// Update accuracy if necessary
				if (playerData.accuracy) {
					playerData.accuracy.current += this.randomIncrement(playerData.accuracy.current, 0, 100, 10);
					playerData.accuracy.current = Math.min(100, Math.max(0, playerData.accuracy.current));
					updateAccuracySVG(playerData);
				}
			});

			// Update team score
			teamsData.forEach(teamData => {
				teamData.scoreValueEl.innerText = Math.round(teamData.currentScore).toLocaleString();
			});

			// Reorder
			if (sortCounter <= 0) {
				reorderPlayers(playersData);
				reorderTeams(teamsData);
				// Reset the counter until next reorder
				sortCounter = 200;
			}

			// Prepare next draw after a timeout
			setTimeout(() => {
				draw(increment);
			}, increment);
		};

		// Start animation
		setTimeout(() => {
			draw(40);
		}, 20);
	}

	private randomIncrement(current: number, min: number, max: number, maxStep: number = 250): number {
		if (max

	<= min) {
			throw new Error("Max value must be greater than min value in randomIncrement");
		}
		const ratio = (current - min) / (max - min);
		const isNegative = Math.random()

	< ratio;
		const magnitude = Math.random() * maxStep;
		return Math.round(isNegative ? -magnitude : magnitude);
	}


}