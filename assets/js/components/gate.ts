import {animateResults} from "./gate/animateResults";
import {prepareFetch, processResponse} from "../includes/apiClient";
import {getGameHighlights} from "../api/endpoints/games";

declare global {
    let tips: string[]
    const tipsDefault: string[]
    let reloadTimer: number
    const timerOffset: number
}

const gameResultsExp = /results-game-(\d+)/;

/**
 * @type {boolean} If the tips component is displaying game highlights
 */
let tipsHighlights: boolean = false;

/**
 * Load gate content, replace the current and run animations if necessary
 * @param path Gate URL path to load.
 * @param reloadTimeout Object that stores the reload timeout for gate. The timeout will be updated if necessary.
 */
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
    // Load content
    prepareFetch(path, 'GET')
        .then(async (response) => {

            // Setup next auto-reload
            clearTimeout(reloadTimeout.timeout);
            if (response.headers.has('x-reload-time')) {
                const time = parseInt(response.headers.get('x-reload-time'));
                if (!isNaN(time)) {
                    reloadTimeout.timeout = setTimeout(() => {
                        loadContent(path, reloadTimeout);
                    }, time * 1000);
                }
            }

            // Copy content
            contentNew.innerHTML = await processResponse(response.headers.get('Content-Type'), response);

            // Find new container classes
            const meta = contentNew.querySelector('meta[name="container-classes"]');
            if (meta) {
                contentNew.className += meta.getAttribute('content');
            }

            // Check if new content is game results
            const isResults = contentNew.classList.contains('results');
            if (isResults) {
                // Check if this game is not already displayed
                const matchNew = contentNew.className.match(gameResultsExp);
                const matchActive = contentActive.className.match(gameResultsExp);
                if (matchNew !== null && matchActive !== null && (matchNew[1] ?? '') === (matchActive[1] ?? '')) {
                    console.log("Results are the same", matchNew, matchActive);
                    return; // Do not animate results in if the game is the same
                }
            }

            // Reset tips
            tips = tipsDefault;
            tipsHighlights = false;

            // Animate the new content in
            contentNew.classList.add('content', 'in');
            contentActive.classList.add('out');
            container.appendChild(contentNew);
            setTimeout(() => {
                removePreviousContent();
                contentNew.classList.remove('in');
            }, 2000);

            // Load game highlights and animate results
            if (isResults) {
                // noinspection JSIgnoredPromiseFromCall
                animateResults(contentNew);
                await replaceTipsWithHighlights(contentNew);
            }

        })
        .catch(response => {
            console.error(response);
        });

    function removePreviousContent() {
        const elements = container.querySelectorAll('.content') as NodeListOf<HTMLDivElement>;
        for (let i = 0; i < elements.length - 1; i++) {
            elements[i].remove();
        }

    }
}

/**
 * Load highlights for a current game and show them instead of tips
 * @param wrapper Results parent element
 */
export async function replaceTipsWithHighlights(wrapper: HTMLElement | Document = document) {
    const gameInfo: HTMLElement = wrapper.querySelector('[data-game]');
    console.log(gameInfo, tipsHighlights);
    // Check if we can get the game code or if the highlights are not already displayed
    if (!gameInfo || tipsHighlights) {
        return;
    }

    const code = gameInfo.dataset.game;
    console.log(gameInfo, code);

    // Load highlights for game
    const highlightsData = await getGameHighlights(code);

    // Parse highlights.
    // Highlights contain player names with their inflection, where the inflection is optional - '(name)<inflection>'
    const highlights: string[] = [];
    highlightsData.forEach(highlight => {
        highlights.push(highlight.description.replace(/@([^@]+)@(?:<([^@]+)>)?/g, (_, group1: string, group2: string | undefined) => {
            return `<strong class="player-name">${group2 ? group2 : group1}</strong>`;
        }));
    });

    // Replace tips with highlights
    tips = highlights;
    tipsHighlights = true;
}

/**
 * Initialize rotating tips component
 */
export function tipsRotations() {
    let counter = 0;
    const tipWrapper = document.querySelector('.tip') as HTMLDivElement;
    if (!tips || !tipWrapper) {
        return;
    }

    // Rotate tips
    setInterval(() => {
        const tipActive = tipWrapper.querySelectorAll('.content') as NodeListOf<HTMLSpanElement>;
        const tipNew = document.createElement('span');
        tipNew.classList.add('content', 'next');

        // Check if we can get the next tip
        if (!tips[counter]) {
            if (tips.length === 0) {
                tips = tipsDefault;
            }
            counter = counter % tips.length;
        }

        // Add a new tip
        tipNew.innerHTML = tips[counter];
        tipWrapper.appendChild(tipNew);

        // Animate old tips out
        tipActive.forEach(el => {
            el.classList.remove('active', 'next');
            el.classList.add('prev');
        });

        counter = (counter + 1) % tips.length;

        // Animate the new tip in
        setTimeout(() => {
            tipNew.classList.remove('next');
            tipNew.classList.add('active');
            tipActive.forEach(el => {
                el.remove();
            });
        }, 1000);
    }, 10000);
}