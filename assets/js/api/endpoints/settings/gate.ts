import {fetchGet} from '../../../includes/apiClient';


export async function getGateScreenSettings(screen: string, params: { [index: string]: string }): Promise<string> {
	return fetchGet(`/settings/gate/settings/${screen}`, params);
}