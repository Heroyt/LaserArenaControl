import Game from "./game";
import {Tooltip} from "bootstrap";

export default class Team {

	key: string;
	row: HTMLElement;
	game: Game;

	playerCount: number;
	name: string;

	$playerCount: HTMLDivElement;
	$name: HTMLInputElement;

    emptyNameTooltip: Tooltip;
    nameTooLongTooltip: Tooltip;

	/**
	 * @param key {String}
	 * @param row {HTMLDivElement}
	 * @param game {Game}
	 */
	constructor(key: string, row: HTMLElement, game: Game) {
		this.key = key;
		this.row = row;
		this.game = game;

		this.playerCount = 0;
		this.name = '';

		this.$playerCount = row.querySelector('.player-count');
		this.$name = row.querySelector('.team-name');

        this.emptyNameTooltip = new Tooltip(
            this.$name,
            {
                title: messages.emptyTeamName,
                trigger: 'manual',
                customClass: 'tooltip-danger',
            }
        );
        this.nameTooLongTooltip = new Tooltip(
            this.$name,
            {
                title: messages.teamNameTooLong,
                trigger: 'manual',
                customClass: 'tooltip-danger',
            }
        );

		this.initEvents();
	}

	clear(): void {
		this.$name.value = this.$name.dataset.default;
		this.update();
	}

	initEvents(): void {
		this.$name.addEventListener('input', () => {
			this.name = this.$name.value;
		});
	}

	update(): void {
		this.recountPlayers();

		if (this.playerCount === 0) {
			this.row.classList.add('d-none');
		} else {
			this.row.classList.remove('d-none');
		}

		this.$playerCount.innerText = this.playerCount.toString();

		this.name = this.$name.value;

        if (this.name.length < this.$name.minLength) {
            this.emptyNameTooltip.show();
        } else {
            this.emptyNameTooltip.hide();
        }
        if (this.name.length > this.$name.maxLength) {
            this.nameTooLongTooltip.show();
        } else {
            this.nameTooLongTooltip.hide();
        }

		this.row.dispatchEvent(
			new Event("update", {
				bubbles: true,
			})
		);
		this.row.dispatchEvent(
			new Event("update-team", {
				bubbles: true,
			})
		);
	}

	recountPlayers(): number {
		this.playerCount = 0;
		this.game.players.forEach(player => {
			if (player.team === this.key) {
				this.playerCount++;
			}
		});
		return this.playerCount;
	}

}