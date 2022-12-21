import {startLoading, stopLoading} from "../../loaders";
import axios, {AxiosResponse} from "axios";
import {TableData} from "../../game/gameInterfaces";
import NewGameGroup from "./groups";

export default class NewGameTables {

	groups: NewGameGroup;
	gameTablesSelect: HTMLSelectElement;

	constructor(groups: NewGameGroup, gameTablesSelect: HTMLSelectElement) {
		this.groups = groups;
		this.gameTablesSelect = gameTablesSelect;


		this.gameTablesSelect.addEventListener('change', async () => {
			await this.selectTable(this.gameTablesSelect.value);
		});

		(document.querySelectorAll('.game-table') as NodeListOf<HTMLDivElement>).forEach(table => {
			this.initTable(table);
		});
		document.getElementById('tables').addEventListener('show.bs.offcanvas', () => {
			this.updateTables();
		});
	}

	initTable(table: HTMLDivElement): void {
		const id = parseInt(table.dataset.id);

		const cleanBtn = table.querySelector('.clean') as HTMLButtonElement;

		table.addEventListener('click', async (e: MouseEvent) => {
			// Prevent trigger if clicked on the cleanBtn
			const target = e.target as HTMLElement;
			if (target === cleanBtn || target.parentElement === cleanBtn) {
				return;
			}
			await this.selectTable(id);
		});

		cleanBtn.addEventListener('click', () => {
			startLoading();
			axios.post(`/tables/${id}/clean`, {})
				.then(() => {
					this.updateTable(id);
					stopLoading();
				})
				.catch(() => {
					stopLoading(false);
				})
		});
	}

	async selectTable(id: number | string) {
		console.log('Selecting table', id);
		const activeTable = document.querySelector('.game-table.active') as HTMLDivElement | null;
		if (activeTable) {
			activeTable.classList.remove('active', 'bg-success', 'text-bg-success');
			if (activeTable.dataset.group) {
				activeTable.classList.add('bg-purple-600', 'text-bg-purple-600');
			} else {
				activeTable.classList.add('bg-purple-400', 'text-bg-purple-400');
			}
		}
		const table = document.querySelector(`.game-table[data-id="${id}"]`) as HTMLDivElement | null;
		if (!table) {
			return;
		}
		console.log(table, table.dataset.group ?? "");
		table.classList.remove('bg-purple-400', 'bg-purple-600', 'text-bg-purple-400', 'text-bg-purple-600');
		table.classList.add('active', 'bg-success', 'text-bg-success');

		if (table.dataset.group) {
			const groupId = parseInt(table.dataset.group);
			let groupDom = this.groups.gameGroupsWrapper.querySelector(`.game-group[data-id="${groupId}"]`) as HTMLDivElement;
			if (!groupDom) {
				// Load group if it doesn't exist (for example if it's disabled)
				startLoading(true);
				await this.groups.loadGroup(groupId);
				groupDom = this.groups.gameGroupsWrapper.querySelector(`.game-group[data-id="${groupId}"]`) as HTMLDivElement;
				stopLoading(true, true);
			}
			// Dispatch a click event on the loadPlayers btn
			groupDom.querySelector('.loadPlayers').dispatchEvent(new Event('click', {bubbles: true}));
		} else {
			this.groups.gameGroupsSelect.value = "";
		}

		this.gameTablesSelect.value = id.toString();
		this.gameTablesSelect.dispatchEvent(new Event('update', {bubbles: true}));
	}

	updateTableData(table: TableData) {
		const tableDom = document.querySelector(`.game-table[data-id="${table.id}"]`) as HTMLDivElement | null;
		if (!tableDom) {
			return;
		}
		const cleanBtn = tableDom.querySelector('.clean') as HTMLButtonElement;

		if (table.group) {
			tableDom.dataset.group = table.group.id.toString();
			if (tableDom.classList.contains('bg-purple-400')) {
				tableDom.classList.remove('bg-purple-400', 'text-bg-purple-400');
				tableDom.classList.add('bg-purple-600', 'text-bg-purple-600');
			}
			cleanBtn.classList.remove('d-none');
		} else {
			tableDom.dataset.group = "";
			if (tableDom.classList.contains('bg-purple-600')) {
				tableDom.classList.remove('bg-purple-600', 'text-bg-purple-600');
				tableDom.classList.add('bg-purple-400', 'text-bg-purple-400');
			}
			cleanBtn.classList.add('d-none');
		}
	}

	updateTable(id: number): void {
		axios.get(`/tables/${id}`)
			.then((response: AxiosResponse<TableData>) => {
				const table = response.data;
				this.updateTableData(table);
			});
	}

	updateTables(): void {
		axios.get('/tables')
			.then((response: AxiosResponse<{ tables: TableData[] }>) => {
				response.data.tables.forEach(this.updateTableData);
			})
	}

}