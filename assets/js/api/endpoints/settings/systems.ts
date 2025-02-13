import {fetchDelete, fetchPost, SuccessResponse} from '../../../includes/apiClient';

export async function addSystemVests(systemId: number, vestCount: number): Promise<SuccessResponse> {
	return fetchPost(`/settings/systems/${systemId}/add-vests`, {count: vestCount});
}

export async function deleteVest(vestId: number): Promise<SuccessResponse> {
	return fetchDelete(`/settings/vests/${vestId}`);
}