export function initCollapse(dom: HTMLElement | Document = document): void {
    const triggers: NodeListOf<HTMLElement> = dom.querySelectorAll('[data-toggle="collapse"]');
    for (const trigger of triggers) {
        if (trigger.dataset.collapseInitialized) {
            continue;
        }
        const selector = trigger.dataset.target ?? trigger.getAttribute('href');
        const targets: NodeListOf<HTMLElement> = dom.querySelectorAll(selector);
        if (targets.length === 0) {
            return;
        }

        let open = targets[0].classList.contains('show');
        trigger.addEventListener('click', () => {
            open = !open;
            for (const target of targets) {
                if (open) {
                    target.classList.add('show');
                    continue;
                }
                target.classList.remove('show');
                target.dispatchEvent(new CustomEvent(open ? 'collapse.open' : 'collapse.close'));
            }
            if (open) {
                trigger.classList.add('collapse-show');
            } else {
                trigger.classList.remove('collapse-show');
            }
        });
        trigger.dataset.collapseInitialized = 'true';

        for (const target of targets) {
            target.addEventListener('collapse.manual.open', () => {
                open = true;
                trigger.classList.add('collapse-show');
            });
            target.addEventListener('collapse.manual.close', () => {
                open = false;
                trigger.classList.remove('collapse-show');
            });
        }
    }
}

export function collapseToggle(element: HTMLElement): void {
    const open = element.classList.contains('show');
    if (open) {
        collapseClose(element);
    } else {
        collapseClose(element);
    }
}

export function collapseShow(element: HTMLElement): void {
    element.classList.add('show');
    element.dispatchEvent(new CustomEvent('collapse.open'));
    element.dispatchEvent(new CustomEvent('collapse.manual.open'));
}

export function collapseClose(element: HTMLElement): void {
    element.classList.remove('show');
    element.dispatchEvent(new CustomEvent('collapse.close'));
    element.dispatchEvent(new CustomEvent('collapse.manual.close'));
}