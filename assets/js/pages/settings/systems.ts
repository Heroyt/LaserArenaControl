import DraggableGrid from '../../components/draggableGrid';
import {fetchPost} from '../../includes/apiClient';
import {startLoading, stopLoading} from '../../loaders';
import {triggerNotificationError} from '../../includes/notifications';
import {addSystemVests, deleteVest} from '../../api/endpoints/settings/systems';

export default function initSystemsSettings() {
	(document.querySelectorAll('.vest') as NodeListOf<HTMLDivElement>).forEach(initVest);

	// Init draggable elements
	document.querySelectorAll<HTMLDivElement>('.vests-layout').forEach(initGrid);

	const newSystemForm = document.getElementById('new-system-form') as HTMLFormElement;
	if (newSystemForm) {
		newSystemForm.addEventListener('submit', e => {
			e.preventDefault();
			const data = new FormData(newSystemForm);
			startLoading();
			fetchPost(newSystemForm.action, data)
				.then(() => {
					window.location.reload();
				})
				.catch((e) => {
					stopLoading(false);
					triggerNotificationError(e);
				});
		});
	}

	for (const tab of document.querySelectorAll<HTMLDivElement>('.system-tab')) {
		const id = parseInt(tab.dataset.id);

		const addVestCount = tab.querySelector<HTMLInputElement>('.add-vests-count');
		const addVests = tab.querySelector<HTMLButtonElement>('.add-vests');
		if (addVests && addVestCount) {
			addVests.addEventListener('click', () => {
				startLoading();
				addSystemVests(id, addVestCount.valueAsNumber)
					.then(() => {
						window.location.reload();
					})
					.catch(async (e) => {
						stopLoading(false);
						await triggerNotificationError(e);
					});
			});
		}
	}
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
	const vestId = parseInt(dom.dataset.id);
	const statusSwitches = dom.querySelectorAll<HTMLInputElement>('.vest-status');
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
	const typeSwitches = dom.querySelectorAll<HTMLInputElement>('.vest-type');
	const vestIcon = dom.querySelector<HTMLSpanElement>('.icon .vest-type');
	const gunIcon = dom.querySelector<HTMLSpanElement>('.icon .gun-type');
	if (vestIcon && gunIcon) {
		typeSwitches.forEach(input => {
			input.addEventListener('change', () => {
				switch (input.value) {
					case 'vest':
						vestIcon.classList.remove('d-none');
						gunIcon.classList.add('d-none');
						break;
					case 'gun':
						vestIcon.classList.add('d-none');
						gunIcon.classList.remove('d-none');
						break;
				}
			});
		});
	}

	const deleteBtn = dom.querySelector<HTMLButtonElement>('.delete');
	if (deleteBtn) {
		deleteBtn.addEventListener('click', () => {
			if (!confirm(deleteBtn.dataset.confirm)) {
				return;
			}

			startLoading();
			deleteVest(vestId)
				.then(() => {
					window.location.reload();
				})
				.catch(async (e) => {
					stopLoading(false);
					await triggerNotificationError(e);
				});
		});
	}
}