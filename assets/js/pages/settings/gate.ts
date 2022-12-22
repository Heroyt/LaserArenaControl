export default function initGateSettings() {
	const backgroundImage = document.getElementById('background-image') as HTMLImageElement;
	const backgroundInput = document.getElementById('background') as HTMLInputElement;
	backgroundInput.addEventListener('change', () => {
		const files = backgroundInput.files[0];
		if (files) {
			const fileReader = new FileReader();
			fileReader.readAsDataURL(files);
			fileReader.addEventListener("load", function () {
				backgroundImage.src = this.result as string;
			});
		}
	});
}