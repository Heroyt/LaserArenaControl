import {Toast} from 'bootstrap';

export type ToastData = {
	title?: string,
	content: string,
	type?: 'info' | 'success' | 'danger' | 'warning' | string,
}

export function triggerNotification(data: ToastData, autohide : boolean = true): void {
	const toasts = document.getElementById('toasts');

	const toast = document.createElement('div');
	toast.classList.add('toast');
	toast.role = 'alert';
	toast.ariaLive = 'assertive';
	toast.ariaAtomic = 'true';

	if (data.title) {
		const header = document.createElement('div');
		header.classList.add('toast-header');

		if (data.type) {
			header.insertAdjacentHTML('beforeend', `<svg class="bd-placeholder-img rounded me-2 text-${data.type}" width="20" height="20"xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMid slice"focusable="false"><rect width="100%" height="100%" style="fill: currentcolor;"></rect></svg>`);
		}
		header.insertAdjacentHTML('beforeend', `<strong class="me-auto">${data.title}</strong>`);
		header.insertAdjacentHTML('beforeend', `<button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>`);

		toast.appendChild(header);
	}

	const body = document.createElement('div');
	body.classList.add('toast-body', 'text-start');
	body.innerHTML = data.content;
	toast.appendChild(body);

	toasts.appendChild(toast);
	const toastObj = new Toast(toast, {
		autohide,
	});
	toastObj.show();
}