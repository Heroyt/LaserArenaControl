import Tooltip from 'bootstrap/js/dist/tooltip';

export function initTooltips(dom: HTMLElement | Document = document): void {
    const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl: HTMLElement) {
        return Tooltip.getOrCreateInstance(tooltipTriggerEl)
    });
}