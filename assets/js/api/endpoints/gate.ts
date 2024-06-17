import {fetchPost, FormSaveResponse} from '../../includes/apiClient';

export async function setGate(system: string, game: number | 'last'): Promise<FormSaveResponse> {
    return fetchPost(`/gate/set/${system}`, {game});
}

export async function setGateLoaded(system: string, game: number | 'last'): Promise<FormSaveResponse> {
    return fetchPost(`/gate/loaded/${system}`, {game});
}

export async function setGateIdle(system: string): Promise<FormSaveResponse> {
    return fetchPost(`/gate/idle/${system}`);
}

export async function setGateEvent(event: string, time: number = 60): Promise<void> {
	return fetchPost('/gate/event', {event, time});
}