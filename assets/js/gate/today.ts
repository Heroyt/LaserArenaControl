import DefaultScreen from './defaultScreen';
import GateScreen from './gateScreen';

export default class TodayScreen extends DefaultScreen {

	isSame(active: GateScreen): boolean {
		if (!(active instanceof TodayScreen)) {
			return false;
		}
		const key = this.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		const keyActive = active.content.querySelector<HTMLElement>('[data-hash]')?.dataset?.hash ?? '';
		return key === keyActive;
	}

}