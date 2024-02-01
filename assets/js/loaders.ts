import {Modal} from 'bootstrap'

const smallLoader = document.getElementById("smallLoader") as HTMLDivElement;
const loadingModalDom = document.getElementById("loader-modal") as HTMLDivElement;
const loadingModal = new Modal(loadingModalDom, {backdrop: "static"});
let loadingCounter = 0;

export function initLoaders(): void {
    document.addEventListener('loading.start', () => {
        startLoading();
    });
    document.addEventListener('loading.small.start', () => {
        startLoading(true);
    });
    document.addEventListener('loading.stop', () => {
        stopLoading();
    });
    document.addEventListener('loading.error', () => {
        stopLoading(false);
    });
    document.addEventListener('loading.small.stop', () => {
        stopLoading(true, true);
    });
    document.addEventListener('loading.small.error', () => {
        stopLoading(false, true);
    });
}

export function startLoading(small: boolean = false): void {
	loadingCounter++;
	if (small) {
		smallLoader.classList.remove("d-none");
		smallLoader.querySelector('.loader').classList.remove("d-none");
	} else {
		loadingModal.show();
		loadingModalDom.querySelector('.loader').classList.remove("d-none");
	}
}

export function stopLoading(success: boolean = true, small: boolean = false) {
	loadingCounter--;
	if (loadingCounter > 0) {
		return;
	}
	const marker = (small ? smallLoader : loadingModalDom).querySelector(success ? ".successAnimation" : ".errorAnimation") as HTMLDivElement;

	if (small) {
		smallLoader.querySelector('.loader').classList.add("d-none");
	} else {
		loadingModalDom.querySelector('.loader').classList.add("d-none");
	}
	marker.classList.add("animated");
	setTimeout(() => {
		if (small) {
			smallLoader.classList.add("d-none");
		} else {
			loadingModal.hide();
		}
		marker.classList.remove("animated");
	}, 1200);
}
