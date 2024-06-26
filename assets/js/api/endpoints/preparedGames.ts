import {customFetch, fetchGet, fetchPost, FormSaveResponse} from '../../includes/apiClient';
import {GameData} from '../../interfaces/gameInterfaces';

export type PreparedGameData = {
	id_game: number,
	datetime: string,
	data: GameData,
	active: 0 | 1 | boolean,
}

export async function deleteAllPreparedGames(): Promise<FormSaveResponse> {
	return customFetch('/prepared', 'DELETE');
}

export async function deletePreparedGame(id: number): Promise<FormSaveResponse> {
	return customFetch(`/prepared/${id}`, 'DELETE');
}

export async function sendPreparedGame(data: GameData): Promise<FormSaveResponse> {
	return fetchPost('/prepared', data);
}

export async function getPreparedGames(): Promise<PreparedGameData[]> {
	return fetchGet('/prepared');
}