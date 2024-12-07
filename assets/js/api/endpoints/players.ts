import {fetchGet, fetchPost, SuccessResponse} from '../../includes/apiClient';

export type ConnectionType = 'rfid'|'laserforce'|'mylasermaxx'|'other';

export type PlayerConnection = {
	type: ConnectionType,
	identifier: string|number,
};

export type LigaPlayer = {
	code: string,
	nickname: string,
	email: string,
	rank: number,
	connections: PlayerConnection[],
};

export type RegisterData = {
	name: string;
	email: string;
	password: string;
}

export async function loadPlayersTable(sort : string, desc: boolean, search: string = '', page: number = 0): Promise<string> {
    const searchParams = new URLSearchParams({search, sort, page: page.toString()});
		if (desc) {
			searchParams.append('desc', '1');
		}
    return fetchGet('/players', searchParams);
}

export async function syncPlayers(): Promise<SuccessResponse> {
    return fetchPost('/players/sync');
}

export async function registerPlayer(data : FormData|RegisterData) : Promise<LigaPlayer> {
	return fetchPost('/public/liga', data);
}

export async function getTopPlayers(): Promise<LigaPlayer[]> {
	return fetchGet('/public/liga/players');
}