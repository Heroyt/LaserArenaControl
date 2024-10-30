import autocomplete, {AutocompleteItem} from 'autocompleter';
import {findGroups} from '../api/endpoints/gameGroups';
import {GameGroupData} from '../interfaces/gameInterfaces';
import {triggerNotificationError} from '../includes/notifications';

interface GroupAutocompleteItem extends AutocompleteItem {
	label: string,
	data: GameGroupData
}

export function initGroupAutocomplete(input : HTMLInputElement, callback: (name: string, id: number) => void) : void {
	autocomplete<GroupAutocompleteItem>({
		input,
		emptyMsg: '',
		minLength: 3,
		preventSubmit: 1,
		debounceWaitMs: 100,
		disableAutoSelect: true,
		fetch: (search, update: (items: GroupAutocompleteItem[]) => void) => {
			findGroups(search)
				.then(response => {
					const autocompleteData: GroupAutocompleteItem[] = [];
					response.forEach(groupData => {
						autocompleteData.push({label: groupData.name, data: groupData});
					});
					update(autocompleteData);
				})
				.catch(e => {
					triggerNotificationError(e);
					update([]);
				});
		},
		onSelect: item => {
			callback(item.data.name, item.data.id);
		}
	});
}