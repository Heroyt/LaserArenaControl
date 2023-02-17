import {SwapOptions} from "sortablejs/plugins";
import Sortable, {SortableOptions} from "sortablejs";
import Game from "./game";

interface SortableSwapOptions extends SortableOptions, SwapOptions {
	game?: Game,
}

interface SortableDragOverEvent {
	evt: Event,
	isOwner: boolean,
	axis: 'vertical' | 'horizontal',
	revert: boolean,
	dragRect: DOMRect,
	targetRect: DOMRect,
	canSort: boolean,
	fromSortable: Sortable,
	activeSortable: Sortable,
	putSortable: Sortable,
	target: HTMLElement,
	dragEl: HTMLElement,
	completed: (insertion: boolean) => boolean,
	onMove: (target: HTMLElement, after?: () => void) => any,
	changed: () => boolean,
	cancel: () => boolean,
}

class CustomSwap {
	pluginName = 'swap';
	defaults: SwapOptions;
	options: SortableSwapOptions;
	sortable: Sortable;

	lastSwapEl: HTMLElement;

	constructor() {
		this.defaults = {
			swapClass: 'sortable-swap-highlight'
		};
	}

	dragStart({dragEl}: { dragEl: HTMLElement }) {
		this.lastSwapEl = dragEl;
	}

	dragOverValid({
					  completed,
					  target,
					  onMove,
					  activeSortable,
					  changed,
					  cancel
				  }: SortableDragOverEvent) {
		if (!activeSortable.options.swap) return;
		let el = this.sortable.el,
			options = this.options;
		if (target && target !== el) {
			let prevSwapEl = this.lastSwapEl;
			if (onMove(target) !== false) {
				this.toggleClass(target, options.swapClass, true);
				this.lastSwapEl = target;
			} else {
				this.lastSwapEl = null;
			}

			if (prevSwapEl && prevSwapEl !== this.lastSwapEl) {
				this.toggleClass(prevSwapEl, options.swapClass, false);
			}
		}
		changed();

		completed(true);
		cancel();
	}

	drop({putSortable, dragEl}: SortableDragOverEvent) {
		const options = this.options;
		this.lastSwapEl && this.toggleClass(this.lastSwapEl, options.swapClass, false);
		if (!options.game) {
			return;
		}
		const game = options.game;
		if (this.lastSwapEl && (options.swap || putSortable && putSortable.options.swap)) {
			if (dragEl !== this.lastSwapEl) {
				const vest1: string | undefined = dragEl.dataset.vest;
				const vest2: string | undefined = this.lastSwapEl.dataset.vest;
				if (!vest1 || !vest2) {
					return;
				}
				const player1 = game.players.get(vest1);
				const player2 = game.players.get(vest2);

				if (!player1 || !player2) {
					return;
				}
				setTimeout(() => {
					// Swap names
					[player1.$name.value, player2.$name.value] = [player2.$name.value, player1.$name.value];

					// Swap teams
					const t1 = player1.team;
					player1._setTeam(player2.team);
					player2._setTeam(t1);

					// Swap skill
					const s1 = player1.skill;
					const rs1 = player1.realSkill;
					player1._setSkill(player2.skill);
					player1.realSkill = player2.realSkill;
					player2._setSkill(s1);
					player2.realSkill = rs1;

					// Swap vip
					const v1 = player1.vip;
					player1._setVip(player2.vip);
					player2._setVip(v1);

					// Swap player code
					const u1 = player1.userCode;
					player1.setUserCode(player2.userCode);
					player2.setUserCode(u1);

					player1.update();
					player2.update();
				}, 50);
			}
		}
	}

	toggleClass(el: HTMLElement, name: string, state: boolean) {
		const R_SPACE = /\s+/g;
		if (el && name) {
			if (el.classList) {
				el.classList[state ? 'add' : 'remove'](name);
			} else {
				var className = (' ' + el.className + ' ').replace(R_SPACE, ' ').replace(' ' + name + ' ', ' ');
				el.className = (className + (state ? ' ' + name : '')).replace(R_SPACE, ' ');
			}
		}
	}

	eventProperties() {
		return {
			swapItem: this.lastSwapEl
		};
	}
}

export default function CustomSwapPlugin() {
	console.log(CustomSwap);
	return Object.assign(CustomSwap, {
		pluginName: 'swap',
		eventProperties: function eventProperties() {
			return {
				swapItem: this.lastSwapEl
			};
		}
	});
}