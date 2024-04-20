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

	wrapper.querySelectorAll<HTMLLabelElement>('.image-input-with-preview').forEach(label => {
		let img: HTMLImageElement | SVGElement = label.querySelector('img, svg');
		const input = label.querySelector('input');
		input.addEventListener('change', () => {
			const files = input.files[0];
			if (files) {
				let type: 'img' | 'svg' = 'img';
				const fileReader = new FileReader();
				if (files.type === 'image/svg+xml') {
					fileReader.readAsText(files);
					type = 'svg';
				} else {
					fileReader.readAsDataURL(files);
				}
				fileReader.addEventListener('load', function () {
					if (type === 'svg') {
						const tmp = document.createElement('div');
						tmp.innerHTML = this.result as string;
						img.remove();
						img = tmp.firstElementChild as SVGElement;
						label.appendChild(img);
					} else {
						if (img instanceof SVGElement) {
							img.remove();
							img = document.createElement('img');
							label.appendChild(img);
						}

						img.src = this.result as string;
					}
				});
			}
		});
	});
}