import {Tooltip} from 'bootstrap';
import {startLoading, stopLoading} from '../loaders';
import {setGate, setGateIdle, setGateLoaded} from '../api/endpoints/gate';

export function gateActions() {
    (document.querySelectorAll('[data-toggle="gate"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
        const id = parseInt(btn.dataset.id);
        const system = btn.dataset.system;
        // Allow for tooltips
        if (btn.title) {
            new Tooltip(btn);
        }
        btn.addEventListener('click', () => {
            startLoading(true);
            setGate(system, id)
                .then(_ => {
                    stopLoading(true, true);
                    if (btn.classList.contains('btn-danger')) {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-success');
                    }
                })
                .catch(response => {
                    console.error(response);
                    stopLoading(false, true);
                });
        });
    });
    (document.querySelectorAll('[data-toggle="gate-loaded"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
        const id = parseInt(btn.dataset.id);
        const system = btn.dataset.system;
        // Allow for tooltips
        if (btn.title) {
            new Tooltip(btn);
        }
        btn.addEventListener('click', () => {
            startLoading(true);
            setGateLoaded(system, id)
                .then(_ => {
                    stopLoading(true, true);
                    if (btn.classList.contains('btn-danger')) {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-success');
                    }
                })
                .catch(response => {
                    console.error(response);
                    stopLoading(false, true);
                });
        });
    });
    (document.querySelectorAll('[data-toggle="gate-idle"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
        const system = btn.dataset.system;
        // Allow for tooltips
        if (btn.title) {
            new Tooltip(btn);
        }
        btn.addEventListener('click', () => {
            startLoading(true);
            setGateIdle(system)
                .then(_ => {
                    stopLoading(true, true);
                    if (btn.classList.contains('btn-danger')) {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-success');
                    }
                })
                .catch(response => {
                    console.error(response);
                    stopLoading(false, true);
                });
        });
    });
}