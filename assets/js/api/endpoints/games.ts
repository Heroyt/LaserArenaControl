import {GameData} from "../../interfaces/gameInterfaces";
import {fetchGet, fetchPost, FormSaveResponse} from "../../includes/apiClient";
import {Highlight} from "../../components/gate/types";

export type LoadedGameResponse = {
    started: boolean,
    finished: boolean,
    currentServerTime: number,
    startTime: number | null,
    gameLength: number,
    loadTime: number,
    playerCount: number,
    teamCount: number,
    mode: object,
    game: GameData
}

export type LastGamesResponse = GameData[];

export type LoadGameResponse = { status: string, mode?: string };

export async function sendLoadGame(system: string, data: FormData): Promise<LoadGameResponse> {
    return fetchPost(`/api/game/load/${system}`, data);
}

export async function getLoadedGame(): Promise<LoadedGameResponse> {
    return fetchGet('/api/game/loaded');
}

export async function getLastGames(limit: number = 10, orderBy: string = 'start', desc: boolean = true, excludeFinished: boolean = true, expand: boolean = true): Promise<LastGamesResponse> {
    return fetchGet('/api/games', {limit, orderBy, desc, excludeFinished, expand});
}

export async function getGameHighlights(code: string): Promise<Highlight[]> {
    return fetchGet(`/laserliga/games/${code}/highlights`);
}

export async function reimportResults(code: string): Promise<FormSaveResponse> {
    return fetchPost(`/api/results/import/${code}`);
}

export async function syncGame(code: string): Promise<FormSaveResponse> {
    return fetchPost(`/api/games/${code}/sync`);
}

export async function recalcGameSkill(code: string): Promise<FormSaveResponse> {
    return fetchPost(`/api/games/${code}/recalcSkill`);
}

export async function changeGameMode(code: string, mode: string): Promise<FormSaveResponse> {
    return fetchPost(`/api/games/${code}/changeMode`, {mode});
}

export async function setGameGroup(code: string, groupId: number): Promise<FormSaveResponse> {
    return fetchPost(`/api/games/${code}/group`, {groupId});
}