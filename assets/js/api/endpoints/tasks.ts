import {ErrorResponse, fetchPost} from '../../includes/apiClient';

export async function planGamePrecacheTask(game: string): Promise<void | ErrorResponse> {
	return fetchPost('/api/tasks/precache', {game});
}

export async function planGameHighlightsTask(game: string): Promise<void | ErrorResponse> {
	return fetchPost('/api/tasks/highlights', {game});
}