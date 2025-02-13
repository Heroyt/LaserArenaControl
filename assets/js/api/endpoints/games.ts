import {GameData} from '../../interfaces/gameInterfaces';
import {fetchGet, fetchPost, FormSaveResponse} from '../../includes/apiClient';
import {Highlight} from '../../components/gate/types';

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

export type LoadGameResponse = { message: string, detail: string|null, values: {mode: string, music: number|null, group: number|'new'|null, groupName: string|null} };

export async function sendLoadGame(system: string | number, data: FormData): Promise<LoadGameResponse> {
	return fetchPost(`/api/game/load/${system}`, data, {'Accept': 'application/json'});
}

export async function getLoadedGame(): Promise<LoadedGameResponse> {
	return fetchGet('/api/game/loaded', null, {'Accept': 'application/json'});
}

export async function getLastGames(limit: number = 10, orderBy: string = 'start', desc: boolean = true, excludeFinished: boolean = true, expand: boolean = true): Promise<LastGamesResponse> {
	return fetchGet('/api/games', {limit, orderBy, desc, excludeFinished, expand}, {'Accept': 'application/json'});
}

export async function getGameHighlights(code: string): Promise<Highlight[]> {
	return fetchGet(`/api/games/${code}/highlights`, null, {'Accept': 'application/json'});
}

export async function reimportResults(code: string): Promise<FormSaveResponse> {
	return fetchPost(`/api/results/import/${code}`, null, {'Accept': 'application/json'});
}

export async function syncGame(code: string): Promise<FormSaveResponse> {
	return fetchPost(`/api/games/${code}/sync`, null, {'Accept': 'application/json'});
}

export async function recalcGameSkill(code: string): Promise<FormSaveResponse> {
	return fetchPost(`/api/games/${code}/recalcSkill`, null, {'Accept': 'application/json'});
}

export async function changeGameMode(code: string, mode: string): Promise<FormSaveResponse> {
	return fetchPost(`/api/games/${code}/changeMode`, {mode}, {'Accept': 'application/json'});
}

export async function setGameGroup(code: string, groupId: number): Promise<FormSaveResponse> {
	return fetchPost(`/api/games/${code}/group`, {groupId}, {'Accept': 'application/json'});
}