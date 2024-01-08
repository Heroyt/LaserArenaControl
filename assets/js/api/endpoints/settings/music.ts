import {Music} from "../../../interfaces/gameInterfaces";
import {customFetch, FormSaveResponse} from "../../../includes/apiClient";

export type MusicUploadResponse = { errors: string[], notices: { type: string, content: string }[], music: Music[] };

export async function deleteMusic(id: number): Promise<FormSaveResponse> {
    return customFetch(`/settings/music/${id}`, 'DELETE');
}