import {startLoading, stopLoading} from "../loaders";

export function initPrintButtons(wrapper: HTMLElement | Document = document): void {
    const btns: NodeListOf<HTMLAnchorElement> = wrapper.querySelectorAll('.print-btn');
    const printIframe = document.createElement('iframe');

    let repeatCount = 0;

    printIframe.style.display = 'none';
    printIframe.onload = () => {
        stopLoading();
        if (printIframe.src) {
            printIframe.contentWindow.print();
            repeatCount = 0;
        }
    };
    document.body.appendChild(printIframe);

    btns.forEach(btn => {
        btn.addEventListener('click', e => {
            if (repeatCount > 0) {
                return;
            }
            e.preventDefault();
            startLoading();
            printIframe.src = btn.href;
            repeatCount++;
        });
    });
}