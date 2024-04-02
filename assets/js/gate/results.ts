import GateScreen from './gateScreen';
import {animateResults} from '../components/gate/animateResults';
import {replaceTipsWithHighlights} from '../components/gate';

const gameResultsExp = /results-game-(\d+)/;

export default class ResultsScreen implements GateScreen {
	content: HTMLDivElement;
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

		replaceTipsWithHighlights(this.content);
		animateResults(this.content);
	}

	animateOut(): void {
		this.content.classList.add('out');
	}

	isSame(active: GateScreen): boolean {
		if (!(active instanceof ResultsScreen)) {
			return false;
		}
		const matchNew = this.content.className.match(gameResultsExp);
		const matchActive = active.content.className.match(gameResultsExp);
		return matchNew !== null && matchActive !== null && (matchNew[1] ?? '') === (matchActive[1] ?? '');
	}

	showTimer(): boolean {
		return false;
	}

}