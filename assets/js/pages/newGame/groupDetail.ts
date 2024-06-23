import {PlayerGroupData, PlayerPayInfo, PriceGroup} from '../../interfaces/gameInterfaces';

export class GroupDetailPlayer {

	player: PlayerGroupData;
	table: HTMLTableElement;
	row: HTMLTableRowElement;
	payment: PlayerPayInfo;

	private readonly priceGroups: Map<number, PriceGroup> = new Map();
	private countsColumn: HTMLTableCellElement;
	private checkbox: HTMLInputElement;
	private select: HTMLSelectElement;
	private readonly onPlayerSelected: (player: GroupDetailPlayer) => void;
	private readonly onPlayerDeselected: (player: GroupDetailPlayer) => void;
	private readonly onPlayerChanged: (player: GroupDetailPlayer) => void;

	constructor(
		player: PlayerGroupData,
		table: HTMLTableElement,
		priceGroups: Map<number, PriceGroup>,
		onPlayerSelected: (player: GroupDetailPlayer) => void,
		onPlayerDeselected: (player: GroupDetailPlayer) => void,
		onPlayerChanged: (player: GroupDetailPlayer) => void,
	) {
		this.table = table;
		this.player = player;
		this.priceGroups = priceGroups;
		this.onPlayerSelected = onPlayerSelected;
		this.onPlayerDeselected = onPlayerDeselected;
		this.onPlayerChanged = onPlayerChanged;
		this.payment = {
			playerName: player.name,
			gamesPlayed: player.gameCodes.length,
			gamesPaid: 0,
			// Null or first price group ID
			priceGroupId: this.priceGroups.size === 0 ? null : this.priceGroups.keys().next().value,
		};

		this.row = document.createElement('tr');
		this.createHTML();
		table.querySelector('tbody').appendChild(this.row);
		this.initEvents();
	}

	setPayment(payment: PlayerPayInfo) {
		this.payment = payment;

		// Default to first price group if possible
		if (!this.payment.priceGroupId && this.priceGroups.size > 0) {
			this.payment.priceGroupId = this.priceGroups.keys().next().value;
		}

		if (this.payment.priceGroupId && this.select) {
			this.select.value = this.payment.priceGroupId.toString();
		}
		this.updatePayInfo();
	}

	setPriceGroup(id: number) {
		this.select.value = id.toString();
		this.payment.priceGroupId = id;
		this.onPlayerChanged(this);
	}

	updatePayInfo() {
		this.countsColumn.innerHTML = `<span class="games-paid">${this.payment.gamesPaid}</span>/<span class="games-played">${this.payment.gamesPlayed}</span>`;
		if (this.payment.gamesPlayed === this.payment.gamesPaid) {
			this.row.classList.add('table-success');
		} else {
			this.row.classList.remove('table-success');
		}
	}

	togglePlayer() {
		if (this.checkbox.checked) {
			this.onPlayerSelected(this);
		} else {
			this.onPlayerDeselected(this);
		}
	}

	deselectPlayer() {
		this.checkbox.checked = false;
	}

	selectPlayer() {
		this.checkbox.checked = true;
	}

	private createHTML() {
		// Add first column (checkbox)
		const checkboxTd = document.createElement('td');
		this.checkbox = document.createElement('input');
		this.checkbox.type = 'checkbox';
		this.checkbox.classList.add('form-check-input');
		this.checkbox.value = this.player.asciiName;
		checkboxTd.appendChild(this.checkbox);
		this.row.appendChild(checkboxTd);

		// Add second column (name)
		const name = document.createElement('td');
		name.innerText = this.player.name;
		this.row.appendChild(name);

		// Maybe add third column (price group)
		if (this.priceGroups.size > 0) {
			const selectTd = document.createElement('td');
			this.select = document.createElement('select');
			this.select.classList.add('form-select');
			for (const [id, priceGroup] of this.priceGroups) {
				this.select.innerHTML += `<option value="${id}">${priceGroup.name}</option>`;
			}
			if (this.payment.priceGroupId) {
				this.select.value = this.payment.priceGroupId.toString();
			}
			selectTd.appendChild(this.select);
			this.row.appendChild(selectTd);
		}

		// Add fourth column (game counts)
		this.countsColumn = document.createElement('td');
		this.countsColumn.classList.add('text-end');
		this.countsColumn.innerHTML = `<span class="games-paid">${this.payment.gamesPaid}</span>/<span class="games-played">${this.payment.gamesPlayed}</span>`;
		this.row.appendChild(this.countsColumn);
	}

	private initEvents() {
		this.checkbox.addEventListener('change', () => {
			this.togglePlayer();
		});
		this.row.addEventListener('click', e => {
			console.log(e.target);
			if (
				e.target instanceof HTMLInputElement
				|| e.target instanceof HTMLButtonElement
				|| e.target instanceof HTMLAnchorElement
			) {
				return;
			}
			this.checkbox.checked = !this.checkbox.checked;
			this.togglePlayer();
		});
		if (this.select) {
			this.select.addEventListener('change', () => {
				this.payment.priceGroupId = parseInt(this.select.value);
				this.onPlayerChanged(this);
			});
		}
	}

}