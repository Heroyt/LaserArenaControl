import Sortable, {SortableOptions} from 'sortablejs';
import {SwapOptions} from 'sortablejs/plugins';

export default class DraggableGrid {

	static mounted : boolean = false;
	public sortable : Sortable;

	constructor(
		private element: HTMLDivElement,
		private columns: number = 15,
		private rows: number = 15,
	) {
		DraggableGrid.mount();
		this.sortable = new Sortable(this.element, {
			swap: true,
			swapClass: 'highlight',
			filter: ".draggable-empty",
			handle: this.element.querySelector('.with-handle') ? '.handle' : undefined,
		});
	}

	static mount() {
		if (this.mounted) {
			return;
		}
		// @ts-ignore
		Sortable.mount(new DraggableGridSwapPlugin());
		this.mounted = true;
	}

	updateColumns(columns : number) {
		if (this.columns < columns) {
			// Add new empty columns
			for (let col = this.columns; col <= columns; col++) {
				for (let row = 1; row <= this.rows; row++) {
					const colEl = document.createElement("div");
					colEl.classList.add('draggable-empty');
					colEl.dataset.row = row.toString();
					colEl.dataset.col = col.toString();
					this.element.insertBefore(colEl, this.element.childNodes[(row-1)*columns + col]);
				}
			}
		}
		else {
			// Remove additional empty columns
			for (let col = this.columns; col > columns; col--) {
				for (let row = 1; row <= this.rows; row++) {
					const emptyColsInRow = this.element.querySelectorAll<HTMLDivElement>(`.draggable-empty[data-row="${row}"]`);
					if (emptyColsInRow.length > 0) {
						emptyColsInRow[emptyColsInRow.length - 1].remove();
					}
				}
			}
		}
		this.columns = columns;
		this.element.style.setProperty('--columns', this.columns.toString());
		this.recalcPositions();
	}

	updateRows(rows: number) {
		if (this.rows < rows) {
			// Adding new rows
			for (let row = this.rows + 1; row <= rows; row++) {
				for (let col = 1; col <= this.columns; col++) {
					const colEl = document.createElement("div");
					colEl.classList.add('draggable-empty');
					colEl.dataset.row =row.toString();
					colEl.dataset.col = col.toString();
					this.element.appendChild(colEl);
				}
			}
		}
		else {
			// Remove empty columns
			const emptyCols = this.element.querySelectorAll<HTMLDivElement>(`.draggable-empty`);
			const toRemove = (this.rows - rows) * this.columns;
			console.log(this.columns, this.rows, rows, 'toRemove', toRemove);
			for (let i = 1; i <= toRemove; i++) {
				emptyCols[emptyCols.length - i].remove();
			}
		}
		this.recalcPositions();
		this.rows = rows;
		this.element.style.setProperty('--rows', this.rows.toString());
	}

	private recalcPositions() : void {
		let index = 0;
		for (const childNode of this.element.childNodes) {
			if (!(childNode instanceof HTMLDivElement)) {
				continue;
			}
			const col = (index % this.columns) + 1;
			const row = Math.floor(index / this.columns) + 1;
			index++;

			if (childNode.dataset) {
				childNode.dataset.col = col.toString();
				childNode.dataset.row = row.toString();
			}

			const colInput = childNode.querySelector<HTMLInputElement>('input.col-input');
			if (colInput) {
				colInput.value = col.toString();
			}
			const rowInput = childNode.querySelector<HTMLInputElement>('input.row-input');
			if (rowInput) {
				rowInput.value = row.toString();
			}
		}
	}

}

interface SortableSwapOptions extends SortableOptions, SwapOptions {
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

class DraggableGridSwap {
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

		if (this.lastSwapEl && (options.swap || putSortable && putSortable.options.swap)) {
			if (dragEl !== this.lastSwapEl) {
				const col1 = dragEl.dataset.col;
				const row1 = dragEl.dataset.row;
				const col1Input = dragEl.querySelector<HTMLInputElement>('input.col-input');
				const row1Input = dragEl.querySelector<HTMLInputElement>('input.row-input');

				const col2 = this.lastSwapEl.dataset.col;
				const row2 = this.lastSwapEl.dataset.row;
				const col2Input = this.lastSwapEl.querySelector<HTMLInputElement>('input.col-input');
				const row2Input = this.lastSwapEl.querySelector<HTMLInputElement>('input.row-input');

				dragEl.dataset.col = col2;
				if (col1Input) {
					col1Input.value = col2;
				}
				if (row1Input) {
					row1Input.value = row2;
				}
				dragEl.dataset.row = row2;

				this.lastSwapEl.dataset.col = col1;
				this.lastSwapEl.dataset.row = row1;
				if (col2Input) {
					col2Input.value = col1;
				}
				if (row2Input) {
					row2Input.value = row1;
				}

				swapNodes(dragEl, this.lastSwapEl);
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

function swapNodes(n1 : HTMLElement, n2 : HTMLElement) {
	let p1 = n1.parentNode,
		p2 = n2.parentNode,
		i1, i2;

	if (!p1 || !p2 || p1.isEqualNode(n2) || p2.isEqualNode(n1)) return;

	i1 = index(n1);
	i2 = index(n2);

	if (p1.isEqualNode(p2) && i1 < i2) {
		i2++;
	}
	p1.insertBefore(n2, p1.children[i1]);
	p2.insertBefore(n1, p2.children[i2]);
}

function index(el : HTMLElement, selector : string = null) {
	let index = 0;
	if (!el || !el.parentNode) {
		return -1;
	}

	while ((el = el.previousElementSibling as HTMLElement)) {
		if (el.nodeName.toUpperCase() !== 'TEMPLATE' && el !== Sortable.clone && (!selector || matches(el, selector))) {
			index++;
		}
	}
	return index;
}

function matches(el : HTMLElement, selector : string = null) {
	if (!selector) return;
	selector[0] === '>' && (selector = selector.substring(1));
	if (el) {
		try {
			if (el.matches) {
				return el.matches(selector);
				// @ts-ignore
			} else if (el.msMatchesSelector) {
				// @ts-ignore
				return el.msMatchesSelector(selector);
			} else if (el.webkitMatchesSelector) {
				return el.webkitMatchesSelector(selector);
			}
		} catch (_) {
			return false;
		}
	}
	return false;
}

export function DraggableGridSwapPlugin() {
	console.log(DraggableGridSwap);
	return Object.assign(DraggableGridSwap, {
		pluginName: 'swap',
		eventProperties: function eventProperties() {
			return {
				swapItem: this.lastSwapEl
			};
		}
	});
}