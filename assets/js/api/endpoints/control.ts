import {fetchGet, fetchPost} from "../../includes/apiClient";

export type GameControlStatus = 'DOWNLOAD' | 'STANDBY' | 'ARMED' | 'PLAYING';
export type GameControlStatusResponse = { status: GameControlStatus };
export type GameControlResponse = { status: 'ok' | 'error', error?: string };
export type GameControlSafeResponse = { status: 'ok' | 'error' | GameControlStatus, error?: string };

export async function getCurrentControlStatus(): Promise<GameControlStatusResponse> {
    return fetchGet('/control/status');
}

export async function controlStop(): Promise<GameControlResponse> {
    return fetchPost('/control/stop');
}

export async function controlLoad(mode: string): Promise<GameControlResponse> {
    return fetchPost('/control/load', {mode});
}

export async function controlLoadSafe(mode: string): Promise<GameControlSafeResponse> {
    return fetchPost('/control/loadSafe', {mode});
}

export async function controlStart(mode: string | null = null): Promise<GameControlResponse> {
    return fetchPost('/control/start', {mode});
}

export async function controlStartSafe(mode: string | null = null): Promise<GameControlSafeResponse> {
    return fetchPost('/control/startSafe', {mode});
}

export async function controlRetryDownload(): Promise<GameControlResponse> {
    return fetchPost('/control/retry');
}

export async function controlCancelDownload(): Promise<GameControlResponse> {
    return fetchPost('/control/cancel');
}