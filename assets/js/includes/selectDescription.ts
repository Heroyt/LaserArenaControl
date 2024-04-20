export function initSelectDescription(dom: HTMLElement | Document = document): void {
	dom.querySelectorAll<HTMLElement>('.select-description').forEach(element => {
		if (!element.dataset.target) {
			// Missing target
			console.log('Missing target');
			return;
		}

		const target = document.querySelector(element.dataset.target) as HTMLSelectElement;
		if (!target) {
			// Invalid target
			console.log('Invalid target');
			return;
		}
		const option = target.querySelector(`option[value="${target.value}"]`) as HTMLOptionElement;
		if (option && option.dataset.description) {
			element.innerText = option.dataset.description;
		} else {
			element.innerText = '';
		}
		target.addEventListener('change', () => {
			const option = target.querySelector(`option[value="${target.value}"]`) as HTMLOptionElement;
			if (option && option.dataset.description) {
				element.innerText = option.dataset.description;
			} else {
				element.innerText = '';
			}
		});
	});
}