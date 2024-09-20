import {loadPlayersTable, syncPlayers} from '../api/endpoints/players';
import {startLoading, stopLoading} from '../loaders';
import {triggerNotification} from '../includes/notifications';

interface TableParams {
	search: string;
	sort: string;
	desc: boolean;
	page: number;
}

export default function initPlayers() {
	const syncPlayersBtn = document.getElementById('sync-players') as HTMLButtonElement;
	if (syncPlayersBtn) {
		syncPlayersBtn.addEventListener('click', () => {
			startLoading(true);
			syncPlayersBtn.disabled = true;
			syncPlayers()
				.then((response) => {
					triggerNotification({
						type: 'success',
						title: response.message,
						content: response.detail,
					});
					stopLoading(true, true);
				})
				.catch(() => {
					stopLoading(false, true);
				})
				.finally(() => {
					syncPlayersBtn.disabled = false;
				})
		});
	}

	let playersTable = document.getElementById('players-table') as HTMLTableElement;

	if (!playersTable) {
		return;
	}

	const loadMoreBtn = document.getElementById('load-more') as HTMLButtonElement;
	if (loadMoreBtn) {
		loadMoreBtn.addEventListener('click', () => {
			loadMore();
		})
	}

	let sortedCol = playersTable.querySelector<HTMLTableCellElement>('[data-sortable="true"].sort-asc, [data-sortable="true"].sort-desc');
	let sort = 'nickname';
	let desc = false;
	let currPage = 0;
	if (sortedCol) {
		sort = sortedCol.dataset.name;
		desc = sortedCol.classList.contains('sort-desc');
	}

	const searchInput = document.getElementById('player-search') as HTMLInputElement;
	if (searchInput) {
		let updateTimer : NodeJS.Timeout;
		searchInput.addEventListener('input', () => {
			if (updateTimer) {
				clearInterval(updateTimer);
			}
			setTimeout(() => {
				reloadTable();
			}, 200);
		});
	}

	initTable(playersTable);

	function initTable(table: HTMLTableElement) : void {
		const sortableCols = table.querySelectorAll<HTMLTableCellElement>('[data-sortable="true"]');
		for (const sortableCol of sortableCols) {
			const name = sortableCol.dataset.name;
			sortableCol.addEventListener('click', () => {
				sort = name;
				desc = sortableCol.classList.contains('sort-asc');
				reloadTable();
			});
		}
	}

	function reloadTable() : void {
		startLoading(true);
		currPage = 0;
		const {search, sort, desc, page} = getTableParams();
		loadPlayersTable(sort, desc, search, page)
			.then(result => {
				const temp = document.createElement('div');
				temp.innerHTML = result;
				const newTable = temp.firstElementChild as HTMLTableElement;
				playersTable.replaceWith(newTable);
				playersTable = newTable;
				initTable(newTable);
				stopLoading(true, true);
			})
			.catch(() => {
				stopLoading(false, true);
			});
	}

	function loadMore() : void {
		startLoading(true);
		currPage++;
		const {search, sort, desc, page} = getTableParams();
		loadPlayersTable(sort, desc, search, page)
			.then(result => {
				const temp = document.createElement('div');
				temp.innerHTML = result;
				const newTable = temp.firstElementChild as HTMLTableElement;
				const rows = newTable.querySelectorAll<HTMLTableRowElement>('tbody tr');
				if (rows.length > 0) {
					playersTable.querySelector('tbody').append(...rows);
				}
				else {
					loadMoreBtn.classList.add('d-none');
				}
				stopLoading(true, true);
			})
			.catch(() => {
				stopLoading(false, true);
			});
	}

	function getTableParams() : TableParams {
		return {
			search: searchInput ? searchInput.value.trim() : '',
			sort: sort,
			desc: desc,
			page: currPage,
		};
	}
}