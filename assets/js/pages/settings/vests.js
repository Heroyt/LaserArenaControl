export default function initVestsSettings() {
	document.querySelectorAll('.vest').forEach(initVest);
}


/**
 *
 * @param dom {Element}
 */
function initVest(dom) {
	const statusSwitches = dom.querySelectorAll('.btn-check');
	statusSwitches.forEach(input => {
		input.addEventListener('change', () => {
			switch (input.value) {
				case 'ok':
					dom.classList.add('bg-success');
					dom.classList.remove('bg-warning', 'bg-danger');
					break;
				case 'playable':
					dom.classList.add('bg-warning');
					dom.classList.remove('bg-success', 'bg-danger');
					break;
				case 'broken':
					dom.classList.add('bg-danger');
					dom.classList.remove('bg-warning', 'bg-success');
					break;
			}
		});
	});
}