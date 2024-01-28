import {fetchPost, FormSaveResponse} from "../../includes/apiClient";

export async function triggerEvent(type: string, message: string | object = ''): Promise<FormSaveResponse> {
    return fetchPost('/api/events', {type, message});
}