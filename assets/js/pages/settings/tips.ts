import {startLoading, stopLoading} from '../../loaders';
import {deleteTip} from '../../api/endpoints/settings/tips';
import {FormSaveResponse} from '../../includes/apiClient';
import {triggerNotificationError} from '../../includes/notifications';

interface SaveResponse extends FormSaveResponse {
	ids: {
		old?: number[]|{
			[index: number]: number
		},
		new?: number[]|{
			[index: number]: number
		}
	}
}

export default function initTips() {
	const form = document.getElementById('tips-settings-form') as HTMLFormElement;
	const table = document.getElementById('tips-table') as HTMLTableElement;
	const addBtn = document.getElementById('add') as HTMLButtonElement;
	const template = document.getElementById('tip-row') as HTMLTemplateElement;

	if (!table) {
		return;
	}

	const body = table.querySelector('tbody');

	if (!body) {
		return;
	}

	for(const row of body.querySelectorAll<HTMLTableRowElement>('tr')) {
		initTipRow(row);
	}

	if (addBtn && template) {
		let newCounter = 0;
		addBtn.addEventListener('click', () => {
			const content = template.innerHTML.replaceAll('#', newCounter.toString());
			const temp = document.createElement('tbody');
			temp.innerHTML = content;
			const row = temp.firstElementChild as HTMLTableRowElement;
			body.appendChild(row);
			initTipRow(row);
			newCounter++;
		});
	}

	if (form) {
		form.addEventListener('autosaved', (e: CustomEvent<SaveResponse>) => {
			if (e.detail.ids.new) {
				const newIds: number[] | { [key: number]: number } = e.detail.ids.new;
				if (newIds instanceof Array) {
					for (let id = 0; id < newIds.length; ++id) {
						const newId = newIds[id];
						replaceNewIds(id, newId);
					}
				} else if (newIds instanceof Object) {
					for (const [id, newId] of Object.entries(newIds)) {
						replaceNewIds(parseInt(id), newId);
					}
				}
			}
			if (e.detail.ids.old) {
				const oldIds: number[] | { [key: number]: number } = e.detail.ids.old;
				if (oldIds instanceof Array) {
					for (let id = 0; id < oldIds.length; ++id) {
						const newId = oldIds[id];
						replaceOldIds(id, newId);
					}
				} else if (oldIds instanceof Object) {
					for (const [id, newId] of Object.entries(oldIds)) {
						replaceOldIds(parseInt(id), newId);
					}
				}
			}
		});
	}

	function replaceNewIds(id: number, newId : number) : void {
		const row = body.querySelector<HTMLTableRowElement>(`tr.new[data-id="${id}"]`);
		if (row) {
			row.classList.remove('new');
			row.classList.add('old');
			row.dataset.id = newId.toString();
			for(const textarea of row.querySelectorAll('textarea')) {
				textarea.name = textarea.name.replace(`new_tip[${id}]`, `tip[${newId}]`);
			}
			row.querySelector<HTMLButtonElement>('.remove').dataset.id = newId.toString();
		}
	}

	function replaceOldIds(id: number, newId : number) : void {
		const row = body.querySelector<HTMLTableRowElement>(`tr.old[data-id="${id}"]`);
		if (row) {
			row.dataset.id = newId.toString();
			for(const textarea of row.querySelectorAll('textarea')) {
				textarea.name = textarea.name.replace(`[${id}]`, `[${newId}]`);
			}
			row.querySelector<HTMLButtonElement>('.remove').dataset.id = newId.toString();
		}
	}

	function initTipRow(row : HTMLTableRowElement) {
		const removeBtn = row.querySelector<HTMLButtonElement>('.remove');
		if (!removeBtn) {
			return;
		}

		removeBtn.addEventListener('click', () => {
			const id = parseInt(row.dataset.id);
			const isNew = row.classList.contains('new');
			if (!isNew && !isNaN(id)) {
				startLoading(true);
				deleteTip(id)
					.then(() => {
						stopLoading(true, true);
						row.remove();
					})
					.catch((e) => {
						stopLoading(false, true);
						triggerNotificationError(e);
					})
			}
			else {
				row.remove();
			}
		})
	}
}