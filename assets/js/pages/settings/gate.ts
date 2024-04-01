import {initCollapse} from '../../includes/collapse';
import {getGateScreenSettings} from '../../api/endpoints/settings/gate';
import {initSelectDescription} from '../../includes/selectDescription';

export default function initGateSettings() {
	const backgroundImage = document.getElementById('background-image') as HTMLImageElement;
	const backgroundInput = document.getElementById('background') as HTMLInputElement;
	backgroundInput.addEventListener('change', () => {
		const files = backgroundInput.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener('load', function () {
				backgroundImage.src = this.result as string;
			});
		}
	});

	initCollapse();

	let newGateCounter = 0;
	let newScreenCounter = 0;
	document.querySelectorAll<HTMLDivElement>('.gate-type').forEach(initGateType);
	const gateTypeWrapper = document.getElementById('gate-types') as HTMLDivElement;
	const addGateType = document.getElementById('add-gate') as HTMLDivElement;
	const gateTypeTemplate = document.getElementById('gate-type-template') as HTMLTemplateElement;
	if (gateTypeTemplate && gateTypeWrapper && addGateType) {
		addGateType.addEventListener('click', () => {
			const tmp = document.createElement('div');
			tmp.innerHTML = gateTypeTemplate.innerHTML
				.replaceAll('#id#', `new-${newGateCounter}`);
			const gate = tmp.firstElementChild as HTMLDivElement;
			gateTypeWrapper.appendChild(gate);
			initGateType(gate);
			newGateCounter++;
		});
	}

	function initGateType(wrapper: HTMLDivElement): void {
		console.log('Init', wrapper);
		initCollapse(wrapper);
		wrapper.querySelectorAll<HTMLDivElement>('.gate-screen').forEach(screen => initGateScreen(screen));

		const screensWrapper = wrapper.querySelector<HTMLDivElement>('.gate-screens');
		const addScreen = wrapper.querySelector<HTMLButtonElement>('.add-screen');
		const screenTemplate = wrapper.querySelector<HTMLTemplateElement>('.new-screen');

		if (screensWrapper && addScreen && screenTemplate) {
			addScreen.addEventListener('click', () => {
				const screen = document.createElement('div');
				screen.classList.add('list-group-item', 'gate-screen', 'gate-screen-new');

				screen.innerHTML = screenTemplate.innerHTML
					.replaceAll('#key#', `new-${newScreenCounter}`);

				screensWrapper.appendChild(screen);
				initGateScreen(screen, true);

				newScreenCounter++;
			});
		}

		const deleteButton = wrapper.querySelector<HTMLButtonElement>('.delete-gate');
		if (deleteButton) {
			deleteButton.addEventListener('click', () => {
				if (wrapper.dataset.deleteKey && wrapper.dataset.id) {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = wrapper.dataset.deleteKey;
					input.value = wrapper.dataset.id;
					wrapper.replaceWith(input);
				} else {
					wrapper.remove();
				}
			});
		}
	}

	function initGateScreen(wrapper: HTMLDivElement, created: boolean = false): void {
		console.log('Init', wrapper);
		initSelectDescription(wrapper);

		const trigger = wrapper.querySelector<HTMLSelectElement>('.screen-trigger');
		const triggerValueWrapper = wrapper.querySelector<HTMLDivElement>('.trigger-value');
		const triggerValue = triggerValueWrapper.querySelector('input');

		const type = wrapper.querySelector<HTMLSelectElement>('.screen-type');
		const settingsWrapper = wrapper.querySelector<HTMLDivElement>('.screen-settings');
		const settingsCache = new Map<string, string>;

		const deleteButton = wrapper.querySelector<HTMLButtonElement>('.delete');

		if (!created) {
			settingsCache.set(type.value, settingsWrapper.innerHTML);
		}

		if (deleteButton) {
			deleteButton.addEventListener('click', () => {
				if (wrapper.dataset.deleteKey && wrapper.dataset.id) {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = wrapper.dataset.deleteKey;
					input.value = wrapper.dataset.id;
					wrapper.replaceWith(input);
				} else {
					wrapper.remove();
				}
			});
		}

		const updateTrigger = () => {
			if (trigger.value === 'custom') {
				triggerValueWrapper.classList.remove('d-none');
			} else {
				triggerValueWrapper.classList.add('d-none');
			}
		};
		const updateSettings = () => {
			const typeValue = type.value;
			console.log(typeValue, Array.from(settingsCache.entries()));
			if (settingsCache.has(typeValue)) {
				settingsWrapper.innerHTML = settingsCache.get(typeValue);
				return;
			}

			settingsWrapper.innerHTML = '';
			getGateScreenSettings(typeValue, Object.assign({}, settingsWrapper.dataset))
				.then((result) => {
					settingsCache.set(typeValue, result);
					settingsWrapper.innerHTML = result;
				})
				.catch(() => {
					settingsCache.set(typeValue, '');
				});
		};

		updateTrigger();
		updateSettings();

		trigger.addEventListener('change', updateTrigger);
		type.addEventListener('change', updateSettings);
	}
}