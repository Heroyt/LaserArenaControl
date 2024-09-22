import {fetchDelete, SuccessResponse} from '../../../includes/apiClient';

export async function deleteTip(id: number): Promise<SuccessResponse> {
    return fetchDelete('/settings/tips/' + id.toString());
}