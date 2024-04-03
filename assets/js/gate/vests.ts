import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';

export default class VestsScreen extends DefaultScreen {

	isSame(active: GateScreen): boolean {
		if (!(active instanceof VestsScreen)) {
			return false;
		}
		const key = this.content.querySelector<HTMLElement>('.vest-grid')?.dataset?.key ?? '';
		const keyActive = active.content.querySelector<HTMLElement>('.vest-grid')?.dataset?.key ?? '';
		return key === keyActive;
	}

	showTimer(): boolean {
		return false;
	}

}