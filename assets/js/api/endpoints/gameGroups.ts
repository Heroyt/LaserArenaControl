import {fetchGet, fetchPost, FormSaveResponse} from '../../includes/apiClient';
import {GameGroupData} from '../../interfaces/gameInterfaces';

export type GameGroupUpdateData = {
	name?: string,
	active?: boolean,
}

export type GameGroupsResponse = GameGroupData[]

export async function findGroups(search: string): Promise<GameGroupsResponse> {
	const searchParams = new URLSearchParams({search});
	return fetchGet('/gameGroups/find', searchParams);
}

export async function updateGameGroup(id: number, data: GameGroupUpdateData): Promise<FormSaveResponse> {
	return fetchPost(`/gameGroups/${id}`, data);
}

export async function getGameGroup(id: number): Promise<GameGroupData> {
	return fetchGet(`/gameGroups/${id}`);
}

export async function getGameGroups(basic: boolean = false, all: boolean = false): Promise<GameGroupsResponse> {
	const params = new URLSearchParams;
	if (basic) {
		params.set('basic', '1');
	}
	if (all) {
		params.set('all', '1');
	}
	return fetchGet(`/gameGroups`, params);
}

export async function createGameGroup(name: string): Promise<GameGroupData> {
	return fetchPost(`/gameGroups`, {name});
}
