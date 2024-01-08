import {fetchPost} from "../../includes/apiClient";

export type GateResponse = { status: 'ok' | 'error', error?: string };

export async function gatesStart(): Promise<GateResponse> {
    return fetchPost('/api/gates/start');
}

export async function gatesStop(): Promise<GateResponse> {
    return fetchPost('/api/gates/stop');
}