import {initCollapse} from '../../includes/collapse';
import {getGateScreenSettings} from '../../api/endpoints/settings/gate';
import {initSelectDescription} from '../../includes/selectDescription';
import {initImageUploadPreview} from '../../includes/imageUploadPreview';

type GateSettingsSaveResponse = {
	success: boolean,
	errors: string[],
	newGateIds: { [index: string]: string },
	newScreenIds: { [index: string]: { [index: string]: string } }
};

export default function initGateSettings() {
	initImageUploadPreview();
	initCollapse();

	const form = document.getElementById('gate-settings-form') as HTMLFormElement;

	form.addEventListener(
		'autosaved',
		(e: CustomEvent<GateSettingsSaveResponse>) => {
			const processedNewScreens = new Set<string>();

			// Rename gate inputs
			Object.entries(e.detail.newGateIds).forEach(([originalGate, gateId]) => {
				const wrapper = form.querySelector<HTMLDivElement>(`#gate-${originalGate}`);
				if (!wrapper) {
					return;
				}
				wrapper.id = wrapper.id.replace(originalGate, gateId);
				wrapper.dataset.id = gateId;
				wrapper.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[name]').forEach(input => {
					input.name = input.name.replace(`new-gate[${originalGate}]`, `gate[${gateId}]`);
				});
				const screenTemplate = wrapper.querySelector<HTMLTemplateElement>('.new-screen');
				if (screenTemplate) {
					screenTemplate.innerHTML = screenTemplate.innerHTML
						.replaceAll(`new-gate[${originalGate}]`, `gate[${gateId}]`)
						.replaceAll(`data-gate-key="${originalGate}"`, `data-gate-key="${gateId}"`)
						.replaceAll(`data-form-name="new-gate"`, `data-form-name="gate"`);
				}

				// Update screens
				if (!(originalGate in e.detail.newScreenIds)) {
					return;
				}
				Object.entries(e.detail.newScreenIds[originalGate]).forEach(([original, id]) => {
					const screenWrapper = wrapper.querySelector<HTMLDivElement>(`.gate-screens > .gate-screen[data-key=${original}]`);
					if (!screenWrapper) {
						return;
					}
					screenWrapper.classList.remove('gate-screen-new');
					screenWrapper.dataset.id = id;
					screenWrapper.dataset.deleteKey = `gate[${gateId}][delete-screens][]`;
					const inputs = screenWrapper.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[name]');
					inputs.forEach(input => {
						input.name = input.name.replace(`[new-screen][${original}]`, `[screen][${id}]`);
					});
					const settingsWrapper = screenWrapper.querySelector<HTMLDivElement>(`.screen-settings[data-screen-key=${original}]`);
					if (settingsWrapper) {
						settingsWrapper.dataset.gateKey = gateId;
						settingsWrapper.dataset.screenKey = id;
						settingsWrapper.dataset.formName = 'gate';
						settingsWrapper.dataset.formName2 = 'screen';
						settingsWrapper.setAttribute('data-gate-key', gateId);
						settingsWrapper.setAttribute('data-screen-key', id);
						settingsWrapper.setAttribute('data-form-name', 'gate');
						settingsWrapper.setAttribute('data-form-name2', 'screen');
					}
				});
				processedNewScreens.add(originalGate);
			});

			Object.entries(e.detail.newScreenIds).forEach(([originalGate, screens]) => {
				if (processedNewScreens.has(originalGate)) {
					return;
				}
				const wrapper = form.querySelector<HTMLDivElement>(`#gate-${originalGate}`);
				if (!wrapper) {
					return;
				}
				Object.entries(screens).forEach(([original, id]) => {
					const screenWrapper = wrapper.querySelector<HTMLDivElement>(`.gate-screens > .gate-screen[data-key=${original}]`);
					if (!screenWrapper) {
						return;
					}
					screenWrapper.classList.remove('gate-screen-new');
					screenWrapper.dataset.id = id;
					screenWrapper.dataset.deleteKey = `gate[${wrapper.dataset.id}][delete-screens][]`;
					const inputs = screenWrapper.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[name]');
					inputs.forEach(input => {
						input.name = input.name.replace(`[new-screen][${original}]`, `[screen][${id}]`);
					});
					const settingsWrapper = screenWrapper.querySelector<HTMLDivElement>(`.screen-settings[data-screen-key=${original}]`);
					if (settingsWrapper) {
						settingsWrapper.dataset.screenKey = id;
						settingsWrapper.dataset.formName2 = 'screen';
						settingsWrapper.setAttribute('data-screen-key', id);
						settingsWrapper.setAttribute('data-form-name2', 'screen');
					}
				});
			});
		});

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
		console.log('Init gate type', wrapper);
		initCollapse(wrapper);

		const screensWrapper = wrapper.querySelector<HTMLDivElement>('.gate-screens:not(:scope .gate-screens .gate-screens)');
		const addScreen = wrapper.querySelector<HTMLButtonElement>('.add-screen:not(.initialized):not(:scope .gate-screens .add-screen)');
		const screenTemplate = wrapper.querySelector<HTMLTemplateElement>('.new-screen:not(:scope .gate-screens .new-screen)');

		if (screensWrapper) {
			screensWrapper.querySelectorAll<HTMLDivElement>(':scope > .gate-screen').forEach(screen => initGateScreen(screen));
		}

		if (screensWrapper && addScreen && screenTemplate) {
			addScreen.classList.add('initialized');
			addScreen.addEventListener('click', () => {
				const screen = document.createElement('div');
				screen.classList.add('list-group-item', 'gate-screen', 'gate-screen-new');
				screen.dataset.key = `new-${newScreenCounter}`;
				screen.setAttribute('data-key', `new-${newScreenCounter}`);

				screen.innerHTML = screenTemplate.innerHTML
					.replaceAll('#key#', `new-${newScreenCounter}`);

				screensWrapper.appendChild(screen);
				initGateScreen(screen, true);

				newScreenCounter++;
			});
		}

		const deleteButton = wrapper.querySelector<HTMLButtonElement>('.delete-gate:not(.initialized)');
		if (deleteButton) {
			deleteButton.classList.add('initialized');
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
		console.log('Init gate screen', wrapper);
		initSelectDescription(wrapper);

		const trigger = wrapper.querySelector<HTMLSelectElement>('.screen-trigger');
		const triggerValueWrapper = wrapper.querySelector<HTMLDivElement>('.trigger-value');
		const triggerValue = triggerValueWrapper.querySelector('input');

		const type = wrapper.querySelector<HTMLSelectElement>('.screen-type');
		const settingsWrapper = wrapper.querySelector<HTMLDivElement>('.screen-settings');
		const settingsCache = new Map<string, string>;

		const deleteButton = wrapper.querySelector<HTMLButtonElement>('.delete:not(.initialized)');

		if (!created) {
			settingsCache.set(type.value, settingsWrapper.innerHTML);
		}

		if (deleteButton) {
			deleteButton.classList.add('initialized');
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

		const initSettings = () => {
			const test = settingsWrapper.querySelector('.gate-screens');
			if (test) {
				settingsWrapper.querySelectorAll<HTMLElement>('[data-toggle="collapse"]')
					.forEach(elem => {
						delete elem.dataset.collapseInitialized;
					});
				initGateType(settingsWrapper);
			}
			initImageUploadPreview(settingsWrapper);
		};
		const updateSettings = () => {
			const typeValue = type.value;
			if (settingsCache.has(typeValue)) {
				settingsWrapper.innerHTML = settingsCache.get(typeValue);
				initSettings();
				return;
			}

			settingsWrapper.innerHTML = '';
			getGateScreenSettings(typeValue, Object.assign({}, settingsWrapper.dataset))
				.then((result) => {
					settingsCache.set(typeValue, result);
					settingsWrapper.innerHTML = result;
					initSettings();
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