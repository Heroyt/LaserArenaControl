import {fetchGet} from "../../includes/apiClient";

export type TranslatedStringResponse = string;

export async function getTranslatedString(string: string, plural: string | null = null, count: number = 1, context: string | null = null): Promise<TranslatedStringResponse> {
    return fetchGet('/api/helpers/translate', {string, plural, count, context});
}