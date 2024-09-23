import DraggableGrid from '../../components/draggableGrid';

export default function initVestsSettings() {
	(document.querySelectorAll('.vest') as NodeListOf<HTMLDivElement>).forEach(initVest);

	// Init draggable elements
	document.querySelectorAll<HTMLDivElement>('.vests-layout').forEach(initGrid);
}

function initGrid(wrapper : HTMLDivElement) {
	const grid = wrapper.querySelector<HTMLDivElement>('.vest-grid');
	const columnsInput = wrapper.querySelector<HTMLInputElement>('.columns-input');
	const rowsInput = wrapper.querySelector<HTMLInputElement>('.rows-input');
	const draggable = new DraggableGrid(grid, columnsInput.valueAsNumber, rowsInput.valueAsNumber);

	columnsInput.addEventListener('input', () => {
		draggable.updateColumns(columnsInput.valueAsNumber);
	});
	rowsInput.addEventListener('input', () => {
		draggable.updateRows(rowsInput.valueAsNumber);
	});
}

/**
 *
 * @param dom {Element}
 */
function initVest(dom: HTMLDivElement): void {
	const statusSwitches = dom.querySelectorAll('.btn-check') as NodeListOf<HTMLInputElement>;
	statusSwitches.forEach(input => {
		input.addEventListener('change', () => {
			switch (input.value) {
				case 'ok':
					dom.classList.add('bg-success');
					dom.classList.remove('bg-warning', 'bg-danger');
					break;
				case 'playable':
					dom.classList.add('bg-warning');
					dom.classList.remove('bg-success', 'bg-danger');
					break;
				case 'broken':
					dom.classList.add('bg-danger');
					dom.classList.remove('bg-warning', 'bg-success');
					break;
			}
		});
	});
}