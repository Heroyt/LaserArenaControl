import axios, {AxiosResponse} from "axios";
import {gameTimer, shuffle} from "../functions";

declare global {
	const tips: string[]
	let reloadTimer: number
	const timerOffset: number
}

interface PlayerData {
	player: HTMLDivElement,
	length: number,
	score: number,
	currentScore: number,
	positionEl: HTMLDivElement,
	scoreEl: HTMLDivElement,
	scoreValueEl: HTMLSpanElement,
	done: boolean,
	team: string,
	ammo?: {
		start: number,
		rest: number,
		current: number,
		el: HTMLSpanElement,
	},
	lives?: {
		start: number,
		rest: number,
		current: number,
		el: HTMLSpanElement,
	},
	accuracy?: {
		current: number,
		value: number,
		radius: number,
		secondDashArray: number,
		svgEl: SVGCircleElement,
		valueEl: SVGTSpanElement,
	},
}

export function loadContent(path: string, reloadTimeout: { timeout: null | NodeJS.Timeout }) {
	const container = document.querySelector('main');
	if (!container) {
		return;
	}
	const contentActive = container.querySelector('.content') as HTMLDivElement;
	if (!contentActive) {
		return;
	}
	const contentNew = document.createElement('div');
	contentNew.classList.add('content', 'in');
	contentActive.classList.add('out');
	axios.get(path)
		.then((response: AxiosResponse<string>) => {
			clearTimeout(reloadTimeout.timeout);
			if (response.headers['x-reload-time']) {
				const time = parseInt(response.headers['x-reload-time']);
				if (!isNaN(time)) {
					reloadTimeout.timeout = setTimeout(() => {
						loadContent(path, reloadTimeout);
					}, time * 1000);
				}
			}
			contentNew.innerHTML = response.data;
			const meta = contentNew.querySelector('meta[name="container-classes"]');
			if (meta) {
				contentNew.className += meta.getAttribute('content');
			}
			container.appendChild(contentNew);
			setTimeout(() => {
				contentActive.remove();
				contentNew.classList.remove('in');
			}, 2000);
			if (contentNew.classList.contains('results')) {
				animateResults(contentNew);
			}
			gameTimer();
		})
		.catch(response => {
			console.error(response);
		});
}

function animateResults(wrapper: HTMLDivElement) {
	const info = wrapper.querySelector('.info') as HTMLDivElement;
	const players = wrapper.querySelectorAll('.player') as NodeListOf<HTMLDivElement>;
	const teams = wrapper.querySelectorAll('.team') as NodeListOf<HTMLDivElement>;
	const playersArray: HTMLDivElement[] = shuffle(Array.from(players));
	const playersData: PlayerData[] = [];
	const teamsData: Map<string, {
		team: HTMLDivElement,
		scoreEl: HTMLDivElement,
		scoreValueEl: HTMLSpanElement,
		score: number,
		currentScore: number
	}> = new Map();
	let maxLength = 0;
	let maxScore = 0;

	info.classList.add('hide');

	players.forEach(player => {
		const score = parseInt(player.dataset.score);
		if (score > maxScore) {
			maxScore = score;
		}
	});
	teams.forEach(team => {
		const scoreEl = team.querySelector('.score') as HTMLDivElement;
		const scoreValueEl = scoreEl.querySelector('.value') as HTMLSpanElement;
		scoreValueEl.innerText = '0';
		team.classList.add('animating');
		teamsData.set(team.dataset.team, {
			team,
			scoreEl,
			scoreValueEl,
			score: parseInt(scoreEl.dataset.score),
			currentScore: 0,
		});
	});
	playersArray.forEach((player, key) => {
		player.style.animationDelay = `${30 * key}ms`;
		player.style.order = key.toString();
		player.classList.add('animate-in');
		player.classList.remove('dead');
		const positionEl = (player.querySelector('.position') as HTMLDivElement);
		positionEl.innerText = `${key + 1}.`;
		const scoreEl = (player.querySelector('.score') as HTMLDivElement);
		const scoreValueEl = scoreEl.querySelector('.value') as HTMLSpanElement;
		scoreValueEl.innerText = `0`;
		scoreEl.classList.remove('text-danger', 'text-gold', 'text-dark-silver', 'text-bronze');
		const score = parseInt(player.dataset.score);
		const length = (8000 + (Math.random() * 4000) + (4000 * (score / maxScore)));
		if (length > maxLength) {
			maxLength = length;
		}
		let playerData: PlayerData = {
			player,
			length,
			score,
			currentScore: 0,
			scoreEl,
			scoreValueEl,
			positionEl,
			done: false,
			team: player.dataset.team,
		};
		if (player.dataset.ammoStart && player.dataset.ammoRest) {
			playerData.ammo = {
				start: parseInt(player.dataset.ammoStart),
				rest: parseInt(player.dataset.ammoRest),
				current: parseInt(player.dataset.ammoStart),
				el: player.querySelector('.ammo .value') as HTMLSpanElement,
			};
			playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
		}
		if (player.dataset.livesStart && player.dataset.livesRest) {
			playerData.lives = {
				start: parseInt(player.dataset.livesStart),
				rest: parseInt(player.dataset.livesRest),
				current: parseInt(player.dataset.livesStart),
				el: player.querySelector('.lives .value') as HTMLSpanElement,
			};
			playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
		}
		if (player.dataset.accuracy) {
			const svg = player.querySelector('.accuracy svg') as SVGElement;
			const circle = svg.querySelector('circle') as SVGCircleElement;
			const radius = circle.r.baseVal.value;
			playerData.accuracy = {
				value: parseInt(player.dataset.accuracy),
				current: 0,
				radius,
				secondDashArray: Math.round(Math.PI * 2 * radius * 10000) / 10000,
				svgEl: circle,
				valueEl: svg.querySelector('text tspan') as SVGTSpanElement,
			};
			playerData.accuracy.valueEl.innerHTML = `${Math.round(playerData.accuracy.current)}%`;
			playerData.accuracy.svgEl.setAttribute('stroke-dasharray', `${Math.round(playerData.accuracy.current * Math.PI * 2 * playerData.accuracy.radius * 100) / 10000} ${playerData.accuracy.secondDashArray}`);
		}
		playersData.push(playerData);
	});

	// Start animation
	setTimeout(() => {
		const playerCount = playersData.length;
		let counter = 0;
		let done = 0;
		console.log(playersData);

		playersData.forEach((playerData, key) => {
			playerData.player.style.animationDelay = null;
			playerData.player.classList.add('animating');
			playerData.player.classList.remove('animate-in');
			playerData.player.style.top = `calc(${key} * (100% - (.2rem * var(--multiplier) * ${playerCount - 1})) / ${playerCount})`;
			playerData.player.style.height = `calc(((100% - (.2rem * var(--multiplier) * ${playerCount - 1})) / ${playerCount}) - 0.4rem * var(--multiplier))`;
		})

		setTimeout(() => {
			incrementStep(20);
		}, 20);

		function incrementStep(increment: number) {
			let totalScore = 0;
			counter += increment;
			//increment = maxIncrement * (Math.pow((counter / maxLength * 1.1), 2) + 0.2);
			if (done === playerCount) {
				// All animations are done
				animationDone();
				return;
			}
			teamsData.forEach(team => {
				team.currentScore = 0;
			});
			playersData.forEach(playerData => {
				if (playerData.done) {
					totalScore += playerData.score;
					if (teamsData.has(playerData.team)) {
						teamsData.get(playerData.team).currentScore += playerData.score;
					}
					return;
				}
				const remaining = playerData.length - counter;
				if (remaining <= 0) {
					playerData.currentScore = playerData.score;
					playerData.scoreValueEl.innerText = playerData.score.toLocaleString();
					totalScore += playerData.score;

					if (teamsData.has(playerData.team)) {
						teamsData.get(playerData.team).currentScore += playerData.score;
					}

					if (playerData.lives) {
						playerData.lives.current = playerData.lives.rest;
						playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
						if (playerData.lives.current <= 0) {
							playerData.player.classList.add('dead');
						}
					}

					if (playerData.ammo) {
						playerData.ammo.current = playerData.ammo.rest;
						playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
						if (playerData.ammo.current <= 0) {
							playerData.player.classList.add('dead');
						}
					}

					if (playerData.accuracy) {
						playerData.accuracy.current = playerData.accuracy.value;
						playerData.accuracy.valueEl.innerHTML = `${Math.round(playerData.accuracy.current)}%`;
						playerData.accuracy.svgEl.setAttribute('stroke-dasharray', `${Math.round(playerData.accuracy.current * Math.PI * 2 * playerData.accuracy.radius * 100) / 10000} ${playerData.accuracy.secondDashArray}`);
					}

					done++;
					playerData.done = true;
					return;
				}

				const remainingPercent = remaining / playerData.length;

				playerData.currentScore = (playerData.score * (1 - remainingPercent)) + (((Math.random() * 500) - 250) * remainingPercent);
				if (teamsData.has(playerData.team)) {
					teamsData.get(playerData.team).currentScore += playerData.currentScore;
				}
				playerData.scoreValueEl.innerText = Math.round(playerData.currentScore).toLocaleString();

				if (playerData.lives) {
					playerData.lives.current = playerData.lives.start - ((playerData.lives.start - playerData.lives.rest) * (1 - remainingPercent));
					playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
					if (playerData.lives.current <= 0) {
						playerData.player.classList.add('dead');
					}
				}

				if (playerData.ammo) {
					playerData.ammo.current = playerData.ammo.start - ((playerData.ammo.start - playerData.ammo.rest) * (1 - remainingPercent));
					if (playerData.ammo.current <= 0) {
						playerData.player.classList.add('dead');
					}
					playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
				}

				if (playerData.accuracy) {
					playerData.accuracy.current = playerData.accuracy.value * (1 - remainingPercent);
					playerData.accuracy.valueEl.innerHTML = `${Math.round(playerData.accuracy.current)}%`;
					playerData.accuracy.svgEl.setAttribute('stroke-dasharray', `${Math.round(playerData.accuracy.current * Math.PI * 2 * playerData.accuracy.radius * 100) / 10000} ${playerData.accuracy.secondDashArray}`);
				}
			});
			if (counter % 200 === 0) {
				playersData.sort((a, b) => {
					return b.currentScore - a.currentScore;
				});
				playersData.forEach((playerData, key) => {
					playerData.player.style.order = key.toString();
					playerData.player.style.zIndex = (playerCount - key).toString();
					playerData.positionEl.innerText = `${key + 1}.`;
					playerData.player.style.top = `calc(${key} * (100% - (.2rem * var(--multiplier) * ${playerCount})) / ${playerCount})`;
				});
			}

			teamsData.forEach(teamData => {
				teamData.scoreValueEl.innerText = Math.round(teamData.currentScore).toLocaleString();
				teamData.team.style.height = `calc(${100 * (teamData.currentScore > 0 ? teamData.currentScore : 0) / (totalScore > 0 ? totalScore : 1)}% - .2rem)`;
			});

			setTimeout(() => {
				incrementStep(increment);
			}, Math.round(increment));
		}

		function animationDone() {
			let totalScore = 0;
			playersData.forEach(playerData => {
				playerData.player.classList.remove('animating');
				playerData.player.classList.add('done');
				playerData.player.style.top = null;
				playerData.player.style.height = null;

				totalScore += playerData.score;

				if (playerData.scoreEl.dataset.class) {
					console.log(playerData.scoreEl.dataset.class);
					playerData.scoreEl.classList.add(playerData.scoreEl.dataset.class.trim());
				}
			});
			teamsData.forEach(teamData => {
				teamData.scoreValueEl.innerText = Math.round(teamData.score).toLocaleString();
				teamData.team.style.height = `calc(${100 * (teamData.score > 0 ? teamData.score : 0) / (totalScore > 0 ? totalScore : 1)}% - .2rem)`;
				teamData.team.classList.remove('animating');
			});

			setTimeout(() => {
				info.classList.remove('hide');
			}, 200)
		}
	}, 2000);
}

export function tipsRotations() {
	let counter = 0;
	const tipWrapper = document.querySelector('.tip') as HTMLDivElement;
	if (!tips || !tipWrapper) {
		return;
	}
	setInterval(() => {
		const tipActive = tipWrapper.querySelectorAll('.content') as NodeListOf<HTMLSpanElement>;
		const tipNew = document.createElement('span');
		tipNew.classList.add('content', 'next');
		tipNew.innerText = tips[counter];
		tipWrapper.appendChild(tipNew);
		tipActive.forEach(el => {
			el.classList.remove('active', 'next');
			el.classList.add('prev');
		});
		counter = (counter + 1) % tips.length;
		setTimeout(() => {
			tipNew.classList.remove('next');
			tipNew.classList.add('active');
			tipActive.forEach(el => {
				el.remove();
			});
		}, 1000);
	}, 10000);
}