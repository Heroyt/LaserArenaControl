import {fetchPost, FormSaveResponse} from "../../../includes/apiClient";

export type VestData = { vest: { [index: string | number]: { status: string, info: string } } };
export type UpdateVestResponse = FormSaveResponse;

export async function updateVests(data: VestData): Promise<UpdateVestResponse> {
    return fetchPost('/settings/vests', data);
}