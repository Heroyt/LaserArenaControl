import {fetchPost, FormSaveResponse} from "../../includes/apiClient";

export async function setGate(system: string, game: number): Promise<FormSaveResponse> {
    return fetchPost(`/gate/set/${system}`, {game});
}

export async function setGateLoaded(system: string, game: number): Promise<FormSaveResponse> {
    return fetchPost(`/gate/loaded/${system}`, {game});
}

export async function setGateIdle(system: string): Promise<FormSaveResponse> {
    return fetchPost(`/gate/idle/${system}`);
}