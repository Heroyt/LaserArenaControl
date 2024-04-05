import GateScreen from './gateScreen';

export default class DefaultScreen implements GateScreen {
	content: HTMLDivElement;
	private removePreviousContent: () => void;

	init(content: HTMLDivElement, removePreviousContent: () => void): void {
		this.content = content;
		this.removePreviousContent = removePreviousContent;
	}

	isSame(active: GateScreen): boolean {
		const key = this.content.querySelector<HTMLElement | null>('[data-key]')?.dataset?.key ?? '';
		const keyActive = active.content.querySelector<HTMLElement | null>('[data-key]')?.dataset?.key ?? '';
		return !(key !== '' && keyActive !== '') || key === keyActive;
	}

	animateIn(): void {
		this.content.classList.add('content', 'in');

		setTimeout(() => {
			this.removePreviousContent();
			this.content.classList.remove('in');
		}, 2000);
	}

	animateOut(): void {
		this.content.classList.add('out');
	}

	showTimer(): boolean {
		return true;
	}

}