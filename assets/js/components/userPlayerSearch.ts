import autocomplete, {AutocompleteItem} from 'autocompleter';
import {UserSearchData} from "../interfaces/userSearchData";
import axios, {AxiosResponse} from "axios";

interface UserSearchAutocompleteItem extends AutocompleteItem {
    label: string,
    data: UserSearchData
}

export function initUserAutocomplete(input: HTMLInputElement, callback: (name: string, code: string, rank: number) => void): void {
    autocomplete<UserSearchAutocompleteItem>({
        input,
        emptyMsg: '',
        minLength: 3,
        preventSubmit: 1,
        debounceWaitMs: 100,
        fetch: (search, update: (items: UserSearchAutocompleteItem[]) => void) => {
            findPlayersLocal(search, true)
                .then((response: AxiosResponse<UserSearchData[]>) => {
                    const autocompleteData: UserSearchAutocompleteItem[] = [];
                    response.data.forEach(playerData => {
                        autocompleteData.push({label: playerData.code + ': ' + playerData.nickname, data: playerData});
                    });
                    update(autocompleteData);
                })
                .catch(() => {
                    update([]);
                });
        },
        onSelect: item => {
            callback(item.data.nickname, item.data.code, item.data.rank);
        }
    });
}

export function findPlayersLocal(search: string, noMail: boolean = false): Promise<AxiosResponse<UserSearchData[]>> {
    const searchParams = new URLSearchParams({search});
    if (noMail) {
        searchParams.append('nomail', '1');
    }
    return axios.get('/players/find?' + searchParams.toString());
}

export function findPlayersPublic(search: string): Promise<AxiosResponse<UserSearchData[]>> {
    const searchParams = new URLSearchParams({search});
    return axios.get('/players/public/find?' + searchParams.toString());
}