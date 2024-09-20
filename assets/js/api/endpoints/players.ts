import {fetchGet} from '../../includes/apiClient';

export async function loadPlayersTable(sort : string, desc: boolean, search: string = '', page: number = 0): Promise<string> {
    const searchParams = new URLSearchParams({search, sort, page: page.toString()});
		if (desc) {
			searchParams.append('desc', '1');
		}
    return fetchGet('/players', searchParams);
}