import {getTranslatedString} from '../api/endpoints/translate';

/**
 * Get the whole URL to given request
 */
export function getLink(request: string[]): string {
    if (prettyUrl) {
        return window.location.origin + '/' + request.join('/');
    } else {
        let query: { [index: string]: string } = {
            lang: document.documentElement.lang
        };
        let i = 0;
        request.forEach(page => {
            if (page === '') {
                return;
            }
            query[`p[${i}]`] = page;
            i++;
        });
        const params = new URLSearchParams(query);
        return window.location.origin + "?" + params.toString();
    }
}

/**
 * Translate a string
 *
 * Caches responses to localStorage object to prevent multiple repeated AJAX requests.
 * @param string {string}
 * @param plural {string|null}
 * @param count {number}
 * @param context {string}
 * @return Promise<string>
 */
export async function lang(string: string, plural: string | null = null, count: number = 1, context: string | null = null): Promise<string> {
    let cacheKey = activeLanguageCode + '-';
    if (context) {
        cacheKey += context;
    }
    cacheKey += ':' + string;
    if (plural) {
        cacheKey += plural;
    }
    cacheKey += count.toString();
    // @ts-ignore
    cacheKey = cacheKey.hashCode().toString(36);
    const test = localStorage.getItem(cacheKey);
    if (test) {
        return new Promise((resolve: ((response: string) => void)) => {
            resolve(test);
        });
    }
    try {
        const response = await getTranslatedString(string, plural, count, context);
        localStorage.setItem(cacheKey, response);
        return new Promise((resolve: ((response: string) => void)) => {
            resolve(response);
        });
    } catch (e) {
        console.error(e);
    }
    return new Promise((_, reject) => {
        reject(string);
    });
}