export default interface GateScreen {
	content: HTMLDivElement;

	init(content: HTMLDivElement, removePreviousContent: () => void): void;

	isSame(active: GateScreen): boolean;

	animateIn(): void;

	animateOut(): void;

	clear(): void;

	showTimer(): boolean;

}

export function isScreen(obj: any): obj is GateScreen {
    return (
        typeof obj.init === 'function' &&
        typeof obj.isSame === 'function' &&
        typeof obj.animateIn === 'function' &&
        typeof obj.animateOut === 'function' &&
        typeof obj.clear === 'function' &&
        typeof obj.showTimer === 'function'
    );
}