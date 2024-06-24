import initJsColor from '../../jscolor';
import {createPriceGroup, deletePriceGroup} from '../../api/endpoints/priceGroups';
import {startLoading, stopLoading} from '../../loaders';

export default function initGeneralSettings() {
	const logoWrapper = document.getElementById('logo-wrapper') as HTMLDivElement;
	const logoInput = document.getElementById('logo') as HTMLInputElement;
	logoInput.addEventListener('change', () => {
		const files = logoInput.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener("load", function () {
				logoWrapper.innerHTML = `<img src="${this.result}" class="img-fluid arena-logo" style="max-height: 200px;" alt="logo" id="arena-logo-image" />`;
			});
		}
	});

	initJsColor();

	// Price groups
	document.querySelectorAll<HTMLDivElement>('.price-group').forEach(initPriceGroup);

	// Create price-group
	const nameInput = document.getElementById('price-group-new-name') as HTMLInputElement;
	const priceInput = document.getElementById('price-group-new-price') as HTMLInputElement;
	const createBtn = document.getElementById('create-price-group') as HTMLButtonElement;
	const priceGroupTemplate = document.getElementById('new-price-group-template') as HTMLTemplateElement;
	const priceGroupsWrapper = document.getElementById('price-groups') as HTMLDivElement;
	if (nameInput && priceInput && createBtn && priceGroupTemplate && priceGroupsWrapper) {
		const tmp = document.createElement('div');
		createBtn.addEventListener('click', async () => {
			startLoading();
			try {
				const priceGroup = await createPriceGroup(nameInput.value, priceInput.valueAsNumber);
				nameInput.value = '';
				priceInput.value = '';

				// Copy template
				tmp.innerHTML = priceGroupTemplate.innerHTML.replaceAll('#', priceGroup.id.toString());
				const priceGroupWrapper = tmp.firstElementChild as HTMLDivElement;
				priceGroupWrapper.querySelector<HTMLInputElement>('.name-input').value = priceGroup.name;
				priceGroupWrapper.querySelector<HTMLInputElement>('.price-input').valueAsNumber = priceGroup.price;

				priceGroupsWrapper.appendChild(priceGroupWrapper);
				initPriceGroup(priceGroupWrapper);
				stopLoading();
			} catch (e) {
				console.error(e);
				stopLoading(false);
			}
		});
	} else {
		console.log(nameInput, priceInput, createBtn, priceGroupTemplate, priceGroupsWrapper);
	}
}

function initPriceGroup(wrapper: HTMLDivElement) {
	const id = parseInt(wrapper.dataset.id);
	const deleteBtn = wrapper.querySelector<HTMLButtonElement>('.delete');
	if (!deleteBtn) {
		return;
	}
	deleteBtn.addEventListener('click', async () => {
		startLoading();
		try {
			console.log(await deletePriceGroup(id));
			stopLoading();
			wrapper.remove();
		} catch (e) {
			console.error(e);
			stopLoading(false);
		}
	});

	// TODO: Maybe add form validation
}