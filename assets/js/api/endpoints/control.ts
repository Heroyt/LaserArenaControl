import {fetchGet, fetchPost} from '../../includes/apiClient';

export type GameControlStatus = 'DOWNLOAD' | 'STANDBY' | 'ARMED' | 'PLAYING';
export type GameControlStatusResponse = { status: GameControlStatus };
export type GameControlResponse = { status: 'ok' | 'error', error?: string };
export type GameControlSafeResponse = { status: 'ok' | 'error' | GameControlStatus, error?: string };

export async function getCurrentControlStatus(systemId: number | null = null): Promise<GameControlStatusResponse> {
	return fetchGet('/control/status' + (systemId ? '/' + systemId : ''));
}

export async function controlStop(systemId: number | null = null): Promise<GameControlResponse> {
	return fetchPost('/control/stop' + (systemId ? '/' + systemId : ''));
}

export async function controlLoad(mode: string, systemId: number | null = null): Promise<GameControlResponse> {
	return fetchPost('/control/load' + (systemId ? '/' + systemId : ''), {mode});
}

export async function controlLoadSafe(mode: string, systemId: number | null = null): Promise<GameControlSafeResponse> {
	return fetchPost('/control/loadSafe' + (systemId ? '/' + systemId : ''), {mode});
}

export async function controlStart(mode: string | null = null, systemId: number | null = null): Promise<GameControlResponse> {
	return fetchPost('/control/start' + (systemId ? '/' + systemId : ''), {mode});
}

export async function controlStartSafe(mode: string | null = null, systemId: number | null = null): Promise<GameControlSafeResponse> {
	return fetchPost('/control/startSafe' + (systemId ? '/' + systemId : ''), {mode});
}

export async function controlRetryDownload(systemId: number | null = null): Promise<GameControlResponse> {
	return fetchPost('/control/retry' + (systemId ? '/' + systemId : ''));
}

export async function controlCancelDownload(systemId: number | null = null): Promise<GameControlResponse> {
	return fetchPost('/control/cancel' + (systemId ? '/' + systemId : ''));
}