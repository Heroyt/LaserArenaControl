import autocomplete, {AutocompleteItem} from 'autocompleter';
import {UserSearchData} from "../interfaces/userSearchData";
import {findUsers} from "../api/endpoints/userSearch";
import {triggerNotificationError} from '../includes/notifications';

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
            findUsers(search)
                .then(response => {
                    const autocompleteData: UserSearchAutocompleteItem[] = [];
                    response.forEach(playerData => {
                        autocompleteData.push({label: playerData.code + ': ' + playerData.nickname, data: playerData});
                    });
                    update(autocompleteData);
                })
                .catch(e => {
		                triggerNotificationError(e);
                    update([]);
                });
        },
        onSelect: item => {
            callback(item.data.nickname, item.data.code, item.data.rank);
        }
    });
}
