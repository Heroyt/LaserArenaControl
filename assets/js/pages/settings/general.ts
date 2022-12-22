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
}