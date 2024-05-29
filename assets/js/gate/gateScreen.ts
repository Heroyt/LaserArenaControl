export default interface GateScreen {
	content: HTMLDivElement;

	init(content: HTMLDivElement, removePreviousContent: () => void): void;

	isSame(active: GateScreen): boolean;

	animateIn(): void;

	animateOut(): void;

	clear(): void;

	showTimer(): boolean;

}