import {Modal, Tooltip} from 'bootstrap';
import {startLoading, stopLoading} from '../../loaders';
import Sortable from 'sortablejs';
import {GameMode, Variation, VariationsValue} from '../../interfaces/gameInterfaces';
import {
	createGameMode,
	createGameModeVariation,
	deleteGameMode,
	GameModeType,
	getAllGameModeVariations,
	getGameModeNames,
	getGameModeSettings,
	getGameModeVariations,
} from '../../api/endpoints/settings/modes';
import {fetchPost, ResponseError} from '../../includes/apiClient';
import {triggerNotificationError} from '../../includes/notifications';
import DOMPurify from 'dompurify';

export default function initModesSettings() {
	const modesWrapper = document.getElementById('modes') as HTMLDivElement;
	const addModeButtons = document.querySelectorAll('.addMode') as NodeListOf<HTMLButtonElement>;
	const modeTemplate = document.getElementById('modeCardTemplate') as HTMLTemplateElement;

	addModeButtons.forEach(btn => {
		const type = btn.dataset.type as GameModeType;
		const system = btn.dataset.system;
		btn.addEventListener('click', () => {
			createMode(system, type);
		});
	});

	// Variations settings
	const variationsModalElement = document.getElementById('game-mode-variations-modal') as HTMLDivElement;
	const variationsModalBody = variationsModalElement.querySelector('.modal-body') as HTMLDivElement;
	const variationsModalForm = variationsModalElement.querySelector('form') as HTMLFormElement;
	const variationsSubmitBtn = variationsModalElement.querySelector('.save') as HTMLButtonElement;
	const variationsModal = new Modal(variationsModalElement);

	// Results settings
	const resultsModalElement = document.getElementById('game-mode-results-modal') as HTMLDivElement;
	const resultsModalForm = resultsModalElement.querySelector('form') as HTMLFormElement;
	const resultsSubmitBtn = resultsModalElement.querySelector('.save') as HTMLButtonElement;
	const resultsModal = new Modal(resultsModalElement);

	// Names settings
	const namesModalElement = document.getElementById('game-mode-names-modal') as HTMLDivElement;
	const namesModalBody = namesModalElement.querySelector('.modal-body') as HTMLDivElement;
	const addNameBtnWrapper = namesModalBody.querySelector('#add-name-wrapper') as HTMLDivElement;
	const namesWrapper = namesModalBody.querySelector('#modeNames') as HTMLDivElement;
	const namesModalForm = namesModalElement.querySelector('form') as HTMLFormElement;
	const namesSubmitBtn = namesModalElement.querySelector('.save') as HTMLButtonElement;
	const namesModal = new Modal(namesModalElement);

	(modesWrapper.querySelectorAll('.mode') as NodeListOf<HTMLDivElement>).forEach(elem => {
		initMode(elem);
	});

	initModals();

	function createMode(system: string, type: GameModeType) {
		startLoading();
		createGameMode(system, type)
			.then(response => {
				const tmp = document.createElement('div');
				tmp.innerHTML = DOMPurify.sanitize(
					modeTemplate.innerHTML
						.replaceAll('#id#', response.id.toString())
						.replaceAll('#type#', response.type),
				);

				(tmp.querySelector('.modeType') as HTMLSelectElement).value = type;
				(tmp.querySelector('.modeName') as HTMLInputElement).value = response.name;
				(tmp.querySelector('.modeLoad') as HTMLInputElement).value = response.loadName;
				(tmp.querySelector('.modeDescription') as HTMLTextAreaElement).value = response.description;

				const modeDiv = tmp.firstElementChild as HTMLDivElement;
				modesWrapper.appendChild(modeDiv);

				initMode(modeDiv);

				stopLoading(true);
			})
			.catch(e => {
				triggerNotificationError(e);
				stopLoading(false);
			});
	}

	function initModals() {
		variationsSubmitBtn.addEventListener('click', () => {
			variationsModal.hide();
		});
		resultsSubmitBtn.addEventListener('click', () => {
			resultsModal.hide();
		});
		namesSubmitBtn.addEventListener('click', () => {
			namesModal.hide();
		});

		variationsModalElement.addEventListener('hide.bs.modal', () => {
			const data = new FormData(variationsModalForm);
			startLoading(true);
			fetchPost(variationsModalForm.action, data)
				.then(_ => {
					stopLoading(true, true);
				})
				.catch(e => {
					triggerNotificationError(e);
					stopLoading(false, true);
				});
		});
		namesModalElement.addEventListener('hide.bs.modal', () => {
			const data = new FormData(namesModalForm);
			startLoading(true);
			fetchPost(namesModalForm.action, data)
				.then(_ => {
					stopLoading(true, true);
				})
				.catch(error => {
					triggerNotificationError(error);
					stopLoading(false, true);
				});
		});
		resultsModalElement.addEventListener('hide.bs.modal', () => {
			const data = new FormData(resultsModalForm);
			startLoading(true);
			fetchPost(resultsModalForm.action, data)
				.then((response: {
					status: string,
					notices: { content: string, type: string }[],
					modes?: { [index: number]: GameMode }
				}) => {
					stopLoading(true, true);
					if (response.modes) {
						Object.entries(response.modes).forEach(([_, mode]) => {
							(document.querySelector('.card.mode') as HTMLDivElement).dataset.mode = JSON.stringify(mode);
						});
					}
				})
				.catch(error => {
					triggerNotificationError(error);
					stopLoading(false, true);
				});
		});
	}

	function initMode(wrapper: HTMLDivElement) {
		const modeId = parseInt(wrapper.dataset.id);
		const modeName = wrapper.querySelector('.modeName') as HTMLInputElement;
		const editVariationsBtn = wrapper.querySelector('.edit-variations') as HTMLButtonElement;
		const editResultsBtn = wrapper.querySelector('.edit-results') as HTMLButtonElement;
		const editModeNamesBtn = wrapper.querySelector('.edit-mode-names') as HTMLButtonElement;
		const deleteBtn = wrapper.querySelector('.delete') as HTMLButtonElement;

		if (editVariationsBtn) {
			editVariationsBtn.addEventListener('click', () => {
				startLoading();
				variationsModalBody.innerHTML = DOMPurify.sanitize(`<p class="fs-sm">${messages.variationsInfo}</p><p class="fs-sm">${messages.variationsInfo2}</p>`); // Clear HTML
				getGameModeVariations(modeId)
					.then(response => {
						const variationsWrapper = document.createElement('div');
						variationsModalBody.appendChild(variationsWrapper);

						const usedVariationIds: number[] = [];

						variationsModalForm.action = '/settings/modes/' + response.mode.id.toString() + '/variations';
						(variationsModalElement.querySelectorAll('.mode-name') as NodeListOf<HTMLElement>).forEach(elem => {
							elem.innerText = response.mode.name;
						});

						const addVariationWrapper = document.createElement('div');
						addVariationWrapper.classList.add('input-group', 'mt-4', 'mb-2');
						addVariationWrapper.innerHTML = DOMPurify.sanitize(
							`<div class="form-floating">`
							+ `<select id="existing-variations" class="form-select"></select>`
							+ `<label for="existing-variations">${messages.existingVariations}</label>`
							+ `</div>`
							+ `<button type="button" class="btn btn-success" id="addExistingVariation">${messages.add}</button>`,
						);

						const existingVariations = addVariationWrapper.querySelector('#existing-variations') as HTMLSelectElement;
						let variations: { [index: number]: Variation } = {};
						getAllGameModeVariations()
							.then(response => {
								variations = response;
								Object.values(response).forEach(variation => {
									if (usedVariationIds.includes(variation.id)) {
										return; // Skip
									}
									const option = document.createElement('option');
									option.value = variation.id.toString();
									option.innerText = variation.name;
									existingVariations.appendChild(option);
								});
							});

						variationsModalBody.appendChild(addVariationWrapper);

						addVariationWrapper.querySelector('#addExistingVariation').addEventListener('click', () => {
							if (!existingVariations.value) {
								return;
							}
							const newId = parseInt(existingVariations.value);
							if (isNaN(newId) || !variations[newId]) {
								return;
							}

							newVariation(variations[newId]);
						});

						const createVariationWrapper = document.createElement('div');
						createVariationWrapper.classList.add('input-group', 'mt-2', 'mb-2');
						createVariationWrapper.innerHTML = DOMPurify.sanitize(
							`<div class="form-floating">` +
							`<input class="form-control" id="newVariationName" placeholder="name" />` +
							`<label for="newVariationName">${messages.newVariation}</label>` +
							`</div>` +
							`<button type="button" class="btn btn-success" id="addNewVariation">${messages.create}</button>`,
						);

						const newVariationName = createVariationWrapper.querySelector('#newVariationName') as HTMLInputElement;
						const addNewVariationButton = createVariationWrapper.querySelector('#addNewVariation') as HTMLButtonElement;
						const newVariationTooltipError = new Tooltip(newVariationName, {
							title: messages.errorEmptyVariationName, trigger: 'manual', customClass: 'tooltip-danger',
						});

						addNewVariationButton.addEventListener('click', () => {
							// Validate value before sending
							if (newVariationName.value.trim() === '') {
								newVariationTooltipError.setContent({
									'.tooltip-inner': messages.errorEmptyVariationName,
								});
								newVariationTooltipError.show();
								return;
							}

							startLoading();
							createGameModeVariation(newVariationName.value.trim())
								.then(response => {
									newVariationName.value = '';
									newVariation(response);
									stopLoading();
								})
								.catch(async error => {
									triggerNotificationError(error);
									stopLoading(false);
									if (error instanceof ResponseError && (await error.data).error) {
										newVariationTooltipError.setContent({
											'.tooltip-inner': (await error.data).error,
										});
										newVariationTooltipError.show();
									}
								});
						});
						newVariationName.addEventListener('input', () => {
							newVariationTooltipError.hide();
						});
						variationsModalBody.appendChild(createVariationWrapper);

						Object.values(response.variations).forEach(({variation, values}) => {
							newVariation(variation, values);
						});

						variationsModal.show();

						function newVariation(variation: Variation, values: VariationsValue[] = []) {
							if (usedVariationIds.includes(variation.id)) {
								alert(messages.errorDuplicateVariation);
								return; // Do not add duplicates
							}
							usedVariationIds.push(variation.id);

							// Remove an option from select
							if (existingVariations) {
								const option = existingVariations.querySelector(`option[value="${variation.id}"]`);
								if (option) {
									option.remove();
								}
							}

							const variationDiv = document.createElement('div');
							variationDiv.classList.add('card', 'my-2', 'w-100');
							variationDiv.innerHTML = DOMPurify.sanitize(
								`<div class="card-body"><div class="input-group mb-2"><div class="form-floating">` +
								`<input type="text" class="form-control" name="variation[${variation.id}][name]" id="variation-name-${variation.id}" value="${variation.name}"/>` +
								`<label for="variation-name-${variation.id}">${messages.variationName}</label>` +
								`</div>` +
								`<input type="checkbox" class="btn-check" id="variation-${variation.id}-public" autocomplete="off" name="variation[${variation.id}][public]" value="1" ${variation.public ? 'checked' : ''}>` +
								`<label data-toggle="tooltip" title="${messages.publicTitle}" class="btn btn-outline-info" for="variation-${variation.id}-public"><i class="fa-solid fa-eye"></i></label>` +
								`</div><div class="values"></div>` +
								`<div class="text-center"><button type="button" class="btn btn-primary add-value w-100"><i class="fa-solid fa-plus"></i></button></div>` +
								`</div>`,
							);
							const variationName = variationDiv.querySelector('.form-control') as HTMLInputElement;
							const tooltip = new Tooltip(variationName, {
								title: messages.errorEmptyVariationName,
								trigger: 'manual',
								customClass: 'tooltip-danger',
							});
							variationName.addEventListener('input', () => {
								if (variationName.value.trim() === '') {
									tooltip.show();
								} else {
									tooltip.hide();
								}
							});
							variationsWrapper.appendChild(variationDiv);

							const valuesWrapper = variationDiv.querySelector('.values') as HTMLDivElement;
							let i = 0;
							values.forEach(value => {
								const valueElement = document.createElement('div');

								valueElement.innerHTML = DOMPurify.sanitize(
									`<div class="input-group">`
									+ `<div class="input-group-text cursor-grab"><i class="fa-solid fa-bars"></i></div>`
									+ `<div class="form-floating">`
									+ `<input type="text" class="form-control" name="variation[${variation.id}][values][${i}][value]" id="variation-value-${variation.id}-${i}" value="${value.value}"/>`
									+ `<label for="variation-value-${variation.id}-${i}">${messages.variationValue}</label>`
									+ `</div>`
									+ `<div class="form-floating">`
									+ `<input type="text" class="form-control" name="variation[${variation.id}][values][${i}][suffix]" id="variation-suffix-${variation.id}-${i}" value="${value.suffix}"/>`
									+ `<label for="variation-suffix-${variation.id}-${i}">${messages.variationSuffix}</label>`
									+ `</div>`
									+ `<input type="hidden" name="variation[${variation.id}][values][${i}][order]" class="orderInput" value="${value.order}">`
									+ `<button class="btn btn-danger deleteVariationValue" type="button"><i class="fa-solid fa-trash"></i></button>`
									+ `</div>`,
								);

								const deleteBtn = valueElement.querySelector('.deleteVariationValue') as HTMLButtonElement;

								deleteBtn.addEventListener('click', e => {
									e.stopPropagation(); // Prevent modal from closing
									valueElement.remove();
								});

								valuesWrapper.appendChild(valueElement);
								i++;
							});

							const addBtn = variationDiv.querySelector('.add-value') as HTMLButtonElement;
							addBtn.addEventListener('click', () => {
								const valueElement = document.createElement('div');

								valueElement.innerHTML = DOMPurify.sanitize(
									`<div class="input-group">`
									+ `<div class="input-group-text cursor-grab"><i class="fa-solid fa-bars"></i></div>`
									+ `<div class="form-floating">`
									+ `<input type="text" class="form-control" name="variation[${variation.id}][values][${i}][value]" id="variation-value-${variation.id}-${i}" value=""/>`
									+ `<label for="variation-value-${variation.id}-${i}">${messages.variationValue}</label>`
									+ `</div>`
									+ `<div class="form-floating">`
									+ `<input type="text" class="form-control" name="variation[${variation.id}][values][${i}][suffix]" id="variation-suffix-${variation.id}-${i}" value=""/>`
									+ `<label for="variation-suffix-${variation.id}-${i}">${messages.variationSuffix}</label>`
									+ `</div>`
									+ `<input type="hidden" name="variation[${variation.id}][values][${i}][order]" class="orderInput" value="${i}">`
									+ `<button class="btn btn-danger deleteVariationValue" type="button"><i class="fa-solid fa-trash"></i></button>`
									+ `</div>`,
								);

								const deleteBtn = valueElement.querySelector('.deleteVariationValue') as HTMLButtonElement;

								deleteBtn.addEventListener('click', e => {
									e.stopPropagation(); // Prevent modal from closing
									valueElement.remove();
								});

								valuesWrapper.appendChild(valueElement);
								i++;
							});

							// noinspection JSUnusedLocalSymbols
							const sortable = new Sortable(valuesWrapper, {
								handle: '.input-group-text', onSort: reorder,
							});

							function reorder() {
								let i = 0;
								(valuesWrapper.querySelectorAll('.orderInput') as NodeListOf<HTMLInputElement>).forEach(input => {
									input.value = i.toString();
									i++;
								});
							}
						}

						stopLoading(true);
					})
					.catch(error => {
						triggerNotificationError(error);
						console.error(error);
					});
			});
		}
		if (editResultsBtn) {
			editResultsBtn.addEventListener('click', () => {
				startLoading();
				getGameModeSettings(modeId)
					.then(data => {
						(resultsModalElement.querySelectorAll('.mode-name') as NodeListOf<HTMLElement>).forEach(elem => {
							elem.innerText = modeName.value;
						});
						(resultsModalElement.querySelectorAll('.form-switch') as NodeListOf<HTMLDivElement>).forEach(elem => {
							const name: string = elem.dataset.name;
							const input = elem.querySelector('input') as HTMLInputElement;
							const label = elem.querySelector('label') as HTMLLabelElement;

							input.name = `mode[${modeId}][settings][${name}]`;
							input.id = `mode-${modeId}-${name}`;
							label.setAttribute('for', `mode-${modeId}-${name}`);
							input.checked = data[name] ?? false;
						});
						resultsModal.show();
						stopLoading(true);
					})
					.catch(error => {
						triggerNotificationError(error);
						stopLoading(false);
					});
			});
		}
		if (editModeNamesBtn) {
			editModeNamesBtn.addEventListener('click', () => {
				startLoading();
				namesWrapper.innerHTML = ''; // Clear
				getGameModeNames(modeId)
					.then(response => {
						namesModalForm.action = `/settings/modes/${modeId}/names`;
						response.forEach(createNameGroup);

						const addBtn = document.createElement('button');
						addBtn.classList.add('btn', 'btn-primary', 'w-100');
						addBtn.type = 'button';
						addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
						addNameBtnWrapper.innerHTML = '';
						addNameBtnWrapper.appendChild(addBtn);
						addBtn.addEventListener('click', e => {
							e.stopPropagation();
							createNameGroup();
						});

						stopLoading();
						namesModal.show();
					})
					.catch(error => {
						triggerNotificationError(error);
						stopLoading(false);
					});

				function createNameGroup(name: string = '') {
					const elem = document.createElement('div');
					elem.classList.add('input-group', 'my-2');
					elem.innerHTML = DOMPurify.sanitize(
						`<input type="text" name="modeNames[]" class="form-control" value="${name}" />`
						+ `<button type="button" class="btn btn-danger remove"><i class="fa-solid fa-trash"></i></button>`,
					);
					const removeBtn = elem.querySelector('.remove') as HTMLButtonElement;
					removeBtn.addEventListener('click', e => {
						e.stopPropagation();
						elem.remove();
					});
					namesWrapper.appendChild(elem);
				}
			});
		}
		if (deleteBtn) {
			deleteBtn.addEventListener('click', () => {
				if (!confirm(messages.areYouSureDelete)) {
					return;
				}

				startLoading();
				deleteGameMode(modeId)
					.then(_ => {
						stopLoading(true);
						wrapper.remove();
					})
					.catch(error => {
						triggerNotificationError(error);
						stopLoading(false);
					});
			});
		}
	}
}