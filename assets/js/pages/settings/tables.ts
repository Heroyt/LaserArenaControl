import {startLoading, stopLoading} from "../../loaders";
import axios from "axios";

export default function initTablesSettings() {

	const tables = document.querySelectorAll('.table-item') as NodeListOf<HTMLDivElement>;
	const tablesSection = document.getElementById('tables') as HTMLDivElement;

	tables.forEach(table => {
		const id = parseInt(table.dataset.id);
		const deleteBtn = table.querySelector('.delete') as HTMLButtonElement;

		deleteBtn.addEventListener('click', () => {
			startLoading();
			axios.delete(`/settings/tables/${id}`)
				.then(() => {
					stopLoading(true);
					table.remove();
				})
				.catch(error => {
					stopLoading(false);
				})
		});

		const colInput = table.querySelector('.col-input') as HTMLInputElement;
		const rowInput = table.querySelector('.row-input') as HTMLInputElement;
		const widthInput = table.querySelector('.width-input') as HTMLInputElement;
		const heightInput = table.querySelector('.height-input') as HTMLInputElement;

		[colInput, rowInput, widthInput, heightInput].forEach(input => {
			input.addEventListener('change', () => {
				table.style.setProperty('grid-column', colInput.value + ' / span ' + widthInput.value);
				table.style.setProperty('grid-row', rowInput.value + ' / span ' + heightInput.value);

				table.dataset.col = colInput.value;
				table.dataset.row = rowInput.value;
				table.dataset.width = widthInput.value;
				table.dataset.height = heightInput.value;

				changeMaxGrid();
			});
		});
	});

	function changeMaxGrid() {
		let maxCol = 0;
		let maxRow = 0;
		tables.forEach(table => {
			const col = parseInt(table.dataset.col) + parseInt(table.dataset.width) - 1;
			const row = parseInt(table.dataset.row) + parseInt(table.dataset.height) - 1;
			if (maxCol < col) {
				maxCol = col;
			}
			if (maxRow < row) {
				maxRow = row;
			}
		});

		tablesSection.style.setProperty('--cols', maxCol.toString());
		tablesSection.style.setProperty('--rows', maxRow.toString());
	}
}