import {fetchGet, fetchPost, FormSaveResponse} from "../../includes/apiClient";
import {GameGroupData} from "../../interfaces/gameInterfaces";

export type GameGroupUpdateData = {
    name?: string,
    active?: boolean,
}

export type GameGroupsResponse = GameGroupData[]

export async function updateGameGroup(id: number, data: GameGroupUpdateData): Promise<FormSaveResponse> {
    return fetchPost(`/gameGroups/${id}`, data);
}

export async function getGameGroup(id: number): Promise<GameGroupData> {
    return fetchGet(`/gameGroups/${id}`);
}

export async function getGameGroups(): Promise<GameGroupsResponse> {
    return fetchGet(`/gameGroups`);
}

export async function createGameGroup(name: string): Promise<GameGroupData> {
    return fetchPost(`/gameGroups`, {name});
}
