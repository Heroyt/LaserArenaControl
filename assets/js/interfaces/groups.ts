import Game from "../game/game";

export interface NewGameGroupInterface {

    game: Game;
    gameGroupsWrapper: HTMLDivElement;
    gameGroupTemplate: HTMLTemplateElement;
    gameGroupsSelect: HTMLSelectElement;

    loadGroup: (groupId: number) => Promise<void>;

}

export enum GroupLoadType {
    TEAMS, PLAYERS,
}