import {Popover} from 'bootstrap';

export function initPopover(dom: HTMLElement | Document = document): void {
    const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="popover"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl: HTMLElement) {
        return Popover.getOrCreateInstance(tooltipTriggerEl)
    });
}