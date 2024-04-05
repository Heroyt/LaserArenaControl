export function initImageUploadPreview(wrapper: Document | HTMLElement = document): void {
	wrapper.querySelectorAll<HTMLInputElement>('input[data-type="file"][data-preview]')
		.forEach(input => {
			const previewImages = document.querySelectorAll<HTMLImageElement>('img' + input.dataset.preview);
			if (previewImages.length === 0) {
				console.error('No preview images found', input.dataset.preview);
				return;
			}
			input.addEventListener('change', () => {
				const files = input.files[0];
				if (files) {
					const fileReader = new FileReader();
					fileReader.readAsDataURL(files);
					fileReader.addEventListener('load', function () {
						for (const previewImage of previewImages) {
							previewImage.src = this.result as string;
						}
					});
				}
			});
		});
}