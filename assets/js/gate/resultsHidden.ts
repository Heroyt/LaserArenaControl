import GateScreen from './gateScreen';
import {PlayerData} from '../components/gate/types';
import ResultsAnimation, {AnimationState, updateAccuracySVG} from '../components/gate/animateResults';

const gameResultsExp = /results-game-(\d+)/;

export default class ResultsHiddenScreen implements GateScreen {
	content: HTMLDivElement;
	private removePreviousContent: () => void;
    private animation: HiddenResultsAnimation;

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

        this.animation = new HiddenResultsAnimation(this.content);
        this.animation.start();
	}

	animateOut(): void {
		this.content.classList.add('out');
        this.animation.stop();
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
}

class HiddenResultsAnimation extends ResultsAnimation {

    protected updatePlayerIteration(playerData: PlayerData, state: AnimationState) {
        // Add a random realistic value to a player
        // The "realistic" value is achieved by limittin the score between min and max scores
        // It the current score approaches the min score, the probability to increase the score should be higher and vice versa
        playerData.currentScore += this.randomIncrement(playerData.currentScore, this.scoreRange.min, this.scoreRange.max);
        playerData.scoreValueEl.innerText = Math.round(playerData.currentScore).toLocaleString();

        // Add player's score to its team
        if (this.teamsData.has(playerData.team)) {
            this.teamsData.get(playerData.team).currentScore += playerData.currentScore;
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
    }

    private randomIncrement(current: number, min: number, max: number, maxStep: number = 250): number {
        if (max <= min) {
            throw new Error("Max value must be greater than min value in randomIncrement");
        }
        const ratio = (current - min) / (max - min);
        const isNegative = Math.random() < ratio;
        const magnitude = Math.random() * maxStep;
        return Math.round(isNegative ? -magnitude : magnitude);
    }

}