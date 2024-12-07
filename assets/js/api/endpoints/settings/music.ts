import {Music} from "../../../interfaces/gameInterfaces";
import {fetchDelete, fetchPost, FormSaveResponse, SuccessResponse} from '../../../includes/apiClient';

export type MusicUploadResponse = { errors: string[], notices: { type: string, content: string }[], music: Music[] };
export type UploadSuccessResponse = SuccessResponse & {values: {url: string|null, name:string|null}}

export async function deleteMusic(id: number): Promise<FormSaveResponse> {
    return fetchDelete(`/settings/music/${id}`);
}

export async function uploadMusicIntro(id: number, file : File): Promise<UploadSuccessResponse> {
	const data = new FormData();
	data.append('intro', file);
	return fetchPost(`/settings/music/${id}/intro`, data);
}

export async function uploadMusicEnding(id: number, file : File): Promise<UploadSuccessResponse> {
	const data = new FormData();
	data.append('ending', file);
	return fetchPost(`/settings/music/${id}/ending`, data);
}

export async function uploadMusicArmed(id: number, file : File): Promise<UploadSuccessResponse> {
	const data = new FormData();
	data.append('armed', file);
	return fetchPost(`/settings/music/${id}/armed`, data);
}