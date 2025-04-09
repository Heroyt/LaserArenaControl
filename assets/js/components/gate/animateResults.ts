import {shuffle} from '../../includes/functions';
import {PlayerData, TeamData} from './types';

/**
 * Run results animation
 * @param wrapper Parent element for the results DOM
 */
export function animateResults(wrapper: HTMLDivElement) {
    const info: HTMLDivElement = wrapper.querySelector('.info');
    const teamsWrapper: HTMLDivElement = wrapper.querySelector('section.teams');
    const playersWrapper: HTMLDivElement = wrapper.querySelector('section.players');
    const players: NodeListOf<HTMLDivElement> = wrapper.querySelectorAll('.player');
    const teams: NodeListOf<HTMLDivElement> = wrapper.querySelectorAll('.team');

    // Randomly shuffle initial position of players and teams
    const playersArray: HTMLDivElement[] = shuffle(Array.from(players));
    const teamsArray: HTMLDivElement[] = shuffle(Array.from(teams));

    const playersData: PlayerData[] = [];
    const teamsData: Map<string, TeamData> = new Map();

    /** @type {number} Maximum animation length for players */
    let maxLength: number = 0;
    let minScore: number = 99999;
    let maxScore: number = 0;

    info.classList.add('hide');

    // Find min and max score for players
    players.forEach(player => {
        const score = parseInt(player.dataset.score);
        if (score > maxScore) {
            maxScore = score;
        }
        if (score < minScore) {
            minScore = score;
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
        const playerData = initPlayer(player, key, minScore, maxScore);

        // Update the maximum length
        if (playerData.length > maxLength) {
            maxLength = playerData.length;
        }

        // Save player data
        playersData.push(playerData);
    });

    // Timeout to let the initial animation finish
    setTimeout(() => {
        animate(playersData, teamsData, playersWrapper, teamsWrapper, info);
    }, 1000);
}

/**
 * Initialize player data
 * @param player Player's element
 * @param key Current key (order)
 * @param minScore Min score of all players
 * @param maxScore Max score of all players
 */
export function initPlayer(player: HTMLDivElement, key: number, minScore: number, maxScore: number): PlayerData {
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
    let playerData: PlayerData = {
        player,
        length,
        position: parseInt(player.dataset.position),
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


function setPlayerFinished(playerData: PlayerData) {
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
 * Reorder all players by their current score
 * @param playersData All players
 */
export function reorderPlayers(playersData: PlayerData[]): void {
    const playerCount: number = playersData.length;

    // Sort players by current score
    playersData.sort((a, b) => {
        return b.currentScore - a.currentScore;
    });
    // Set position style
    playersData.forEach((playerData, key) => {
        playerData.player.style.order = key.toString();
        playerData.player.style.zIndex = (playerCount - key).toString();
        playerData.positionEl.innerText = `${key + 1}.`;
        playerData.player.style.top = `calc(${key} * (100% - (.2rem * var(--multiplier) * ${playerCount})) / ${playerCount})`;
    });
}

// 8 rem in px
const minTeamHeight = parseFloat(getComputedStyle(document.body).fontSize) * 8;

/**
 * Reorder all teams by their current score
 * @param teamsData
 */
export function reorderTeams(teamsData: Map<string, TeamData>): void {
    if (teamsData.size === 0) {
        return;
    }

    // Sort teams by their score in ascending order
    const sortedTeams: TeamData[] = Array.from(teamsData.values())
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

    let percentSum = 0;
    // Calculate heights from the smallest to largest
    sortedTeams.forEach((teamData, key) => {
        const percent = Math.min(
            Math.max(
                100 * (teamData.currentScore - minScore) / totalScore,
                minHeightPercent
            ),
            100 - percentSum
        );
        percentSum += percent;
        teamData.team.style.height = `calc(${percent}% - .2rem)`;
        teamData.team.style.top = `calc(${100 - percentSum}% + ${key * 0.2}rem)`;
        teamData.team.style.order = (sortedTeams.length - key).toString();
    })

}

/**
 * Run the results animation
 * @param playersData All players
 * @param teamsData All teams
 * @param playersWrapper Players wrapper element
 * @param teamsWrapper Teams wrapper element
 * @param info Info wrapper (QR code)
 */
function animate(playersData: PlayerData[], teamsData: Map<string, TeamData>, playersWrapper: HTMLDivElement, teamsWrapper: HTMLDivElement, info: HTMLDivElement) {
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
    /**
     * @type {number} How many players are finished animating
     */
    let done: number = 0;

    // Finish the player animation-in
    playersData.forEach((playerData, key) => {
        playerData.player.style.animationDelay = null;
        playerData.player.classList.add('animating');
        playerData.player.classList.remove('animate-in');

        // Set the absolute positions for each player to allow position switching
        playerData.player.style.top = `calc(${key} * (100% - (.2rem * var(--multiplier) * ${playerCount - 1})) / ${playerCount})`;
        playerData.player.style.height = `calc(((100% - (.2rem * var(--multiplier) * ${playerCount - 1})) / ${playerCount}) - 0.4rem * var(--multiplier))`;
    });

    // Rewrite the default flex display to allow position switching
    playersWrapper.style.display = 'block';
    if (teamsWrapper) {
        teamsWrapper.style.display = 'block';
    }

    // Start animation
    setTimeout(() => {
        draw(20);
    }, 20);

    /**
     * The main animation function - runs 1 step of the animation using a set time increment
     * @param increment Set time increment in milliseconds
     */
    function draw(increment: number): void {

        // Calculate the real increment until last draw
        const realIncrement = Date.now().valueOf() - now.valueOf();
        counter += realIncrement;
        sortCounter -= realIncrement;
        now = Date.now();

        //increment = maxIncrement * (Math.pow((counter / maxLength * 1.1), 2) + 0.2);
        if (done === playerCount) {
            // All animations are done
            animationDone();
            return;
        }

        // Reset team's current (animated) score
        teamsData.forEach(team => {
            team.currentScore = 0;
        });

        // Increment each player
        playersData.forEach(playerData => {
            // Skip players that are finished
            if (playerData.done) {

                // Add player's score to its team
                if (teamsData.has(playerData.team)) {
                    teamsData.get(playerData.team).currentScore += playerData.score;
                }

                return;
            }

            // Calculate remaining time in milliseconds
            const remaining = playerData.length - counter;

            // Animation should be finished
            if (remaining <= 0) {
                setPlayerFinished(playerData);

                // Add player's score to its team
                if (teamsData.has(playerData.team)) {
                    teamsData.get(playerData.team).currentScore += playerData.score;
                }

                done++;
                return;
            }

            const remainingPercent = remaining / playerData.length;

            // Calculate current score with some random bias
            playerData.currentScore = (playerData.score * (1 - remainingPercent)) + (((Math.random() * 500) - 250) * remainingPercent);
            playerData.scoreValueEl.innerText = Math.round(playerData.currentScore).toLocaleString();

            // Add player's score to its team
            if (teamsData.has(playerData.team)) {
                teamsData.get(playerData.team).currentScore += playerData.currentScore;
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
    }

    /**
     * Finish the animation
     */
    function animationDone() {
        let totalScore = 0;

        // Reset players
        playersData.forEach(playerData => {
            playerData.player.classList.remove('animating');
            playerData.player.classList.add('done');

            // Reset custom styles
            playerData.player.style.top = null;
            playerData.player.style.height = null;

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
                    .replaceAll("\t", '')
                    .replaceAll("\n", '')
                    // Split classes if multiple are present
                    .split(' ')
                    // Remove empty strings
                    .filter(value => {
                        return value.trim() === '';
                    });
                playerData.scoreEl.classList.add(...classes);
            }
        });
        // Reset the manually set wrapper display
        playersWrapper.style.display = null;
        if (teamsWrapper) {
            teamsWrapper.style.display = null;
        }

        // Reset teams
        teamsData.forEach(teamData => {
            teamData.scoreValueEl.innerText = Math.round(teamData.score).toLocaleString();
            teamData.team.style.top = null;
            teamData.team.style.height = `calc(${100 * (teamData.score > 0 ? teamData.score : 0) / (totalScore > 0 ? totalScore : 1)}% - .2rem)`;
            teamData.team.classList.remove('animating');
        });

        // Show the results info block (QR code)
        setTimeout(() => {
            info.classList.remove('hide');
        }, 200)
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