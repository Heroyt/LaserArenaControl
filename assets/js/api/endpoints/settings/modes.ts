import {GameMode, ModeSettings, Variation, VariationCollection} from "../../../interfaces/gameInterfaces";
import {customFetch, fetchGet, fetchPost, FormSaveResponse} from "../../../includes/apiClient";

export type GameModeType = 'solo' | 'SOLO' | 'team' | 'TEAM';

export type VariationsResponse = {
    mode: GameMode,
    variations: VariationCollection
}
export type AllVariationsResponse = {
    [index: number]: Variation
}

export async function createGameMode(system: string, type: GameModeType): Promise<GameMode> {
    return fetchPost(`/settings/modes/new/${system}/${type}`);
}

export async function getGameModeVariations(modeId: number): Promise<VariationsResponse> {
    return fetchGet(`/settings/modes/${modeId}/variations`);
}

export async function getGameModeSettings(modeId: number): Promise<ModeSettings> {
    return fetchGet(`/settings/modes/${modeId}/settings`);
}

export async function getGameModeNames(modeId: number): Promise<string[]> {
    return fetchGet(`/settings/modes/${modeId}/names`);
}

export async function getAllGameModeVariations(): Promise<AllVariationsResponse> {
    return fetchGet('/settings/modes/variations');
}

export async function createGameModeVariation(name: string): Promise<Variation> {
    return fetchPost('/settings/modes/variations', {name});
}

export async function deleteGameMode(modeId: number): Promise<FormSaveResponse> {
    return customFetch(`/settings/modes/${modeId}`, 'DELETE');
}