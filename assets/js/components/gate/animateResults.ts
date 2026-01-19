import {shuffle} from '../../includes/functions';
import {PlayerData, TeamData} from './types';

export type PlayerPositions = Map<number, { x: number, y: number }>;

export type AnimationState = {
    now: number;
    counter: number;
    sortCounter: number;
    done: number;
    playerCount: number;
}

// 8 rem in px
const minTeamHeight = parseFloat(getComputedStyle(document.body).fontSize) * 8;

export default class ResultsAnimation {

    protected stopFlag: boolean = false;
    protected positions: PlayerPositions = new Map;
    protected readonly info: HTMLDivElement;
    protected readonly teamsWrapper: HTMLDivElement;
    protected readonly playersWrapper: HTMLDivElement;
    protected readonly players: NodeListOf<HTMLDivElement>;
    protected readonly teams: NodeListOf<HTMLDivElement>;
    protected readonly playersArray: HTMLDivElement[];
    protected readonly teamsArray: HTMLDivElement[];
    protected readonly playersData: PlayerData[] = [];
    protected readonly teamsData: Map<string, TeamData> = new Map();
    protected readonly scoreRange: { min: number; max: number };

    constructor(
        protected readonly wrapper: HTMLDivElement
    ) {
        this.info = this.wrapper.querySelector('.info');
        this.teamsWrapper = this.wrapper.querySelector('section.teams');
        this.playersWrapper = this.wrapper.querySelector('section.players');
        this.players = this.wrapper.querySelectorAll('.player');
        this.teams = this.wrapper.querySelectorAll('.team');

        // Randomly shuffle initial position of players and teams
        this.playersArray = shuffle(Array.from(this.players));
        this.teamsArray = shuffle(Array.from(this.teams));

        /** @type {number} Maximum animation length for players */
        let maxLength: number = 0;
        this.scoreRange = findMinMaxScores(this.players);
        let minScore: number = this.scoreRange.min;
        let maxScore: number = this.scoreRange.max;

        this.hideInfo();

        // Initialize teams - save team data and reset the score
        for (const team of this.teamsArray) {
            const key = this.teamsArray.indexOf(team);
            const scoreEl = team.querySelector('.score') as HTMLDivElement;
            const scoreValueEl = scoreEl.querySelector('.value') as HTMLSpanElement;
            scoreValueEl.innerText = '0';
            team.classList.add('animating');
            team.style.order = key.toString();
            this.teamsData.set(
                team.dataset.team,
                {
                    team,
                    scoreEl,
                    scoreValueEl,
                    score: parseInt(scoreEl.dataset.score),
                    currentScore: 0,
                }
            );
        }

        // Initialize players - prepare animation and parse all information
        for (const player of this.playersArray) {
            const key = this.playersArray.indexOf(player);
            const playerData = this.initPlayer(player, key, minScore, maxScore);

            // Update the maximum length
            if (playerData.length > maxLength) {
                maxLength = playerData.length;
            }

            // Save player data
            this.playersData.push(playerData);
        }
    }

    start() {
        // Timeout to let the initial animation finish
        setTimeout(() => {
            this.animate();
        }, 1000);
    }

    stop() {
        this.stopFlag = true;
    }

    hideInfo() {
        this.info.classList.add('hide');
    }

    showInfo() {
        this.info.classList.remove('hide');
    }

    draw(increment: number, state: AnimationState): void {
        // Calculate the real increment until last draw
        const realIncrement = Date.now().valueOf() - state.now.valueOf();
        state.counter += realIncrement;
        state.sortCounter -= realIncrement;
        state.now = Date.now();

        if (this.stopFlag || state.done === state.playerCount) {
            // All animations are done
            this.animationDone();
            return;
        }

        // Reset team's current (animated) score
        this.teamsData.forEach(team => {
            team.currentScore = 0;
        });

        // Increment each player
        for (const playerData of this.playersData) {
            this.updatePlayerIteration(playerData, state);
        }

        // Update team score
        this.teamsData.forEach(teamData => {
            this.updateTeamIteration(teamData, state);
        });

        // Reorder
        if (state.sortCounter <= 0) {
            this.reorderPlayers();
            this.reorderTeams();
            // Reset the counter until next reorder
            state.sortCounter = 200;
        }

        // Prepare next draw after a timeout
        setTimeout(() => {
            this.draw(increment, state);
        }, increment);
    }

    /**
     * Reorder all players by their current score
     */
    protected reorderPlayers(): void {
        if (!this.positions || this.positions.size === 0) {
            this.positions = this.getPlayerPositions();
        }

        const playerCount: number = this.playersData.length;

        // Sort players by current score
        this.playersData.sort((a, b) => {
            return b.currentScore - a.currentScore;
        });
        // Set position style
        for (const playerData of this.playersData) {
            const key = this.playersData.indexOf(playerData);
            const position = key + 1;
            const location = this.positions.get(position);
            playerData.player.style.zIndex = (playerCount - key).toString();
            playerData.positionEl.innerText = `${position}.`;

            // Calculate translation
            const translateX = location.x - playerData.originalLocation.x;
            const translateY = location.y - playerData.originalLocation.y;

            playerData.player.style.translate = `${translateX}px ${translateY}px`;

            // playerData.player.style.top = `calc(${key} * (100% - (.2rem * var(--multiplier) * ${playerCount})) / ${playerCount})`;
        }
    }

    /**
     * Find all location positions of players for all game positions
     */
    protected getPlayerPositions(): PlayerPositions {
        const positions: PlayerPositions = new Map();
        for (const player of this.playersData) {
            const rect = player.player.getBoundingClientRect();
            positions.set(player.currentPosition, {x: rect.x, y: rect.y});
            player.originalLocation = {x: rect.x, y: rect.y};
        }
        return positions;
    }

    /**
     * Reorder all teams by their current score
     */
    protected reorderTeams(): void {
        if (this.teamsData.size === 0) {
            return;
        }

        // Sort teams by their score in ascending order
        const sortedTeams: TeamData[] = Array.from(this.teamsData.values())
            .sort((a, b) => {
                return a.currentScore - b.currentScore;
            });

        const totalScore = sortedTeams
            .map(data => {
                return Math.abs(data.currentScore);
            })
            .reduce((sum, current) => {
                return sum + current;
            });

        const minScore = Math.min(sortedTeams[0].currentScore, 0);

        const totalHeight = sortedTeams[0].team.parentElement.getBoundingClientRect().height;
        const minHeightPercent = 100 * minTeamHeight / totalHeight;

        if (totalScore <= 0) {
            // All teams have 0 or negative score - set equal heights
            const equalPercent = 100 / sortedTeams.length;
            sortedTeams.forEach((teamData, key) => {
                teamData.team.style.height = `calc(${equalPercent}% - .2rem)`;
                teamData.team.style.top = `calc(${equalPercent * key}% + ${key * 0.2}rem)`;
                teamData.team.style.order = (sortedTeams.length - key).toString();
            });
            return;
        }

        let percentSum = 0;
        // Calculate heights from the smallest to largest
        sortedTeams.forEach((teamData, key) => {
            const percent = Math.min(
                Math.max(
                    100 * (teamData.currentScore - minScore) / totalScore,
                    minHeightPercent,
                ),
                100 - percentSum,
            );
            percentSum += percent;
            teamData.team.style.height = `calc(${percent}% - .2rem)`;
            teamData.team.style.top = `calc(${100 - percentSum}% + ${key * 0.2}rem)`;
            teamData.team.style.order = (sortedTeams.length - key).toString();
        });

    }

    protected animate() {
        const state: AnimationState = {
            now: Date.now(),
            counter: 0,
            sortCounter: 200,
            done: 0,
            playerCount: this.playersData.length,
        }

        // Finish the player animation-in
        for (const playerData of this.playersData) {
            // playerData.player.style.animationDelay = null;
            playerData.player.classList.add('animating');
            playerData.player.classList.remove('animate-in');
        }

        // Rewrite the default flex display to allow position switching
        // playersWrapper.style.display = 'block';
        if (this.teamsWrapper) {
            this.teamsWrapper.style.display = 'block';
        }

        // Start animation
        setTimeout(() => {
            this.reorderPlayers();
            this.reorderTeams();
            this.draw(40, state);
        }, 20);

        /**
         * The main animation function - runs 1 step of the animation using a set time increment
         * @param increment Set time increment in milliseconds
         */


        /**
         * Finish the animation
         */
    }

    protected animationDone() {
        let totalScore = 0;

        // Sort players by current score
        this.playersData.sort((a, b) => {
            return b.currentScore - a.currentScore;
        });

        // Reset animation delays
        for (const key in this.playersData) {
            const playerData = this.playersData[key];
            playerData.player.style.animationDelay = `${30 * parseInt(key)}ms`;
            playerData.player.style.order = key;
        }

        // Reset players
        this.playersData.forEach(playerData => {
            playerData.player.classList.remove('animating');
            playerData.player.classList.add('done');

            // Reset custom styles
            playerData.player.style.height = null;
            playerData.player.style.translate = null;

            // Reset player score and position value
            playerData.scoreValueEl.innerText = playerData.score.toLocaleString();
            playerData.positionEl.innerText = `${playerData.position}.`;

            totalScore += playerData.score;

            if (playerData.scoreEl.dataset.class) {
                // Reset score classes
                const classes = playerData
                    .scoreEl
                    .dataset
                    .class
                    // Cleanup the string
                    .trim()
                    .replaceAll('\t', '')
                    .replaceAll('\n', '')
                    // Split classes if multiple are present
                    .split(' ')
                    // Remove empty strings
                    .filter(value => {
                        return value.trim() === '';
                    });
                if (classes.length > 0) {
                    playerData.scoreEl.classList.add(...classes);
                }
            }
        });
        // Reset the manually set wrapper display
        // playersWrapper.style.display = null;
        if (this.teamsWrapper) {
            this.teamsWrapper.style.display = null;
        }

        // Reset teams
        this.teamsData.forEach(teamData => {
            teamData.scoreValueEl.innerText = Math.round(teamData.score).toLocaleString();
            teamData.team.style.top = null;
            teamData.team.style.height = `calc(${100 * (teamData.score > 0 ? teamData.score : 0) / (totalScore > 0 ? totalScore : 1)}% - .2rem)`;
            teamData.team.classList.remove('animating');
        });

        // Show the results info block (QR code)
        setTimeout(() => {
            this.showInfo();
        }, 200);
    }

    protected updateTeamIteration(teamData: TeamData, state: AnimationState) {
        teamData.scoreValueEl.innerText = Math.round(teamData.currentScore).toLocaleString();
    }

    protected updatePlayerIteration(playerData: PlayerData, state: AnimationState) {
        // Skip players that are finished
        if (playerData.done) {

            // Add player's score to its team
            if (this.teamsData.has(playerData.team)) {
                this.teamsData.get(playerData.team).currentScore += playerData.score;
            }

            return;
        }

        // Calculate remaining time in milliseconds
        const remaining = playerData.length - state.counter;

        // Animation should be finished
        if (remaining <= 0) {
            this.setPlayerFinished(playerData);

            // Add player's score to its team
            if (this.teamsData.has(playerData.team)) {
                this.teamsData.get(playerData.team).currentScore += playerData.score;
            }

            state.done++;
            return;
        }

        const remainingPercent = remaining / playerData.length;

        // Calculate current score with some random bias
        playerData.currentScore = (playerData.score * (1 - remainingPercent)) + (((Math.random() * 500) - 250) * remainingPercent);
        playerData.scoreValueEl.innerText = Math.round(playerData.currentScore).toLocaleString();

        // Add player's score to its team
        if (this.teamsData.has(playerData.team)) {
            this.teamsData.get(playerData.team).currentScore += playerData.currentScore;
        }

        // Update lives if necessary
        if (playerData.lives) {
            playerData.lives.current = playerData.lives.start - ((playerData.lives.start - playerData.lives.rest) * (1 - remainingPercent));
            playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
            if (playerData.lives.current <= 0) {
                playerData.player.classList.add('dead');
            }
        }

        // Update ammo if necessary
        if (playerData.ammo) {
            playerData.ammo.current = playerData.ammo.start - ((playerData.ammo.start - playerData.ammo.rest) * (1 - remainingPercent));
            if (playerData.ammo.current <= 0) {
                playerData.player.classList.add('dead');
            }
            playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
        }

        // Update accuracy if necessary
        if (playerData.accuracy) {
            playerData.accuracy.current = playerData.accuracy.value * (1 - remainingPercent);
            updateAccuracySVG(playerData);
        }
    }

    protected setPlayerFinished(playerData: PlayerData) {
        // Set final score
        playerData.currentScore = playerData.score;
        playerData.scoreValueEl.innerText = playerData.score.toLocaleString();

        // Set final lives if necessary
        if (playerData.lives) {
            playerData.lives.current = playerData.lives.rest;
            playerData.lives.el.innerText = Math.round(playerData.lives.current).toLocaleString();
            if (playerData.lives.current <= 0) {
                playerData.player.classList.add('dead');
            }
        }

        // Set final ammo if necessary
        if (playerData.ammo) {
            playerData.ammo.current = playerData.ammo.rest;
            playerData.ammo.el.innerText = Math.round(playerData.ammo.current).toLocaleString();
            if (playerData.ammo.current <= 0) {
                playerData.player.classList.add('dead');
            }
        }

        // Set final accuracy if necessary
        if (playerData.accuracy) {
            playerData.accuracy.current = playerData.accuracy.value;
            updateAccuracySVG(playerData);
        }

        playerData.done = true;
    }

    /**
     * Initialize player data
     * @param player Player's element
     * @param key Current key (order)
     * @param minScore Min score of all players
     * @param maxScore Max score of all players
     */
    protected initPlayer(player: HTMLDivElement, key: number, minScore: number, maxScore: number): PlayerData {
        // Prepare the animation in
        player.style.animationDelay = `${30 * key}ms`;
        player.style.order = key.toString();
        player.classList.add('animate-in');
        player.classList.remove('dead');

        // Set initial (random) position
        const positionEl = (player.querySelector('.position') as HTMLDivElement);
        positionEl.innerText = `${key + 1}.`;

        // Set initial (0) score
        const scoreEl = (player.querySelector('.score') as HTMLDivElement);
        const scoreValueEl = scoreEl.querySelector('.value') as HTMLSpanElement;
        scoreValueEl.innerText = `0`;
        scoreEl.classList.remove('text-danger', 'text-gold', 'text-dark-silver', 'text-bronze');

        // Get real player's score
        const score = parseInt(player.dataset.score);

        // Calculate animation length with some random bias (in milliseconds)
        const length = (3000 + (Math.random() * 4000) + (2000 * ((score - minScore) / (maxScore - minScore))));

        // Prepare player data
        const position = parseInt(player.dataset.position);
        const boundingRect = player.getBoundingClientRect();
        let playerData: PlayerData = {
            player,
            length,
            position,
            currentPosition: key + 1,
            originalLocation: {x: boundingRect.x, y: boundingRect.y},
            score,
            currentScore: 0,
            scoreEl,
            scoreValueEl,
            positionEl,
            done: false,
            team: player.dataset.team,
        };
        // Additional information for some game modes
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
            updateAccuracySVG(playerData);
        }
        return playerData;
    }
}


/**
 * Calculate and update the accuracy svg for player
 * @param playerData
 */
export function updateAccuracySVG(playerData: PlayerData) {
    playerData.accuracy.valueEl.innerHTML = `${Math.round(playerData.accuracy.current)}%`;
    playerData.accuracy.svgEl.setAttribute('stroke-dasharray', `${Math.round(playerData.accuracy.current * Math.PI * 2 * playerData.accuracy.radius * 100) / 10000} ${playerData.accuracy.secondDashArray}`);
}

export function findMinMaxScores(players: NodeListOf<HTMLDivElement>): { min: number, max: number } {
    let minScore: number = 99999;
    let maxScore: number = 0;
    for (const player of players) {
        const score = parseInt(player.dataset.score);
        if (score > maxScore) {
            maxScore = score;
        }
        if (score < minScore) {
            minScore = score;
        }
    }
    return {min: minScore, max: maxScore};
}