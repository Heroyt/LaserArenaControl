import {customFetch, FormSaveResponse, RequestMethod} from './apiClient';
import {startLoading, stopLoading} from '../loaders';

export function initAutoSaveForm() {
    // Autosave form
    (document.querySelectorAll('form.autosave') as NodeListOf<HTMLFormElement>).forEach(form => {
        const method = form.method.toUpperCase() as RequestMethod;
        const url = form.action;

        let lastData = new FormData(form);
        let autosaving = 0;
        const lastSave = document.querySelectorAll(`.last-save[data-target="#${form.id}"]`) as NodeListOf<HTMLDivElement>;
        const saveButtons = form.querySelectorAll(`[data-action="autosave"]`) as NodeListOf<HTMLButtonElement>;
        const save = (smallLoader = true) => {
            let newData = new FormData(form);
            let changed = false;
            if (!smallLoader) {
                startLoading(false);
            }
            newData.forEach((value, key) => {
                if (changed || key === "_csrf_token" || key === 'action') {
                    return;
                }
                if (!lastData.has(key)) {
                    console.log("Changed - new key", key, value)
                    changed = true;
                } else if (value instanceof File) {
                    if (value.name !== (lastData.get(key) as File).name) {
                        console.log("Changed - new file", key, value)
                        changed = true;
                    }
                } else if (JSON.stringify(lastData.getAll(key)) !== JSON.stringify(newData.getAll(key))) {
                    console.log("Changed - new value", key, value)
                    changed = true;
                }
            });
            if (!changed) {
                lastData.forEach((value, key) => {
                    if (changed || key === "_csrf_token" || key === 'action') {
                        return;
                    }
                    if (!newData.has(key)) {
                        console.log("Changed - removed key", key, value)
                        changed = true;
                    }
                });
            }
            if (changed && autosaving === 0) {
                autosaving++;
                lastData = newData;
                newData.append("action", "autosave");
                if (smallLoader) startLoading(smallLoader);
                saveButtons.forEach(button => {
                    button.disabled = true;
                });
                customFetch(url, method, {body: newData})
                    .then((result: FormSaveResponse) => {
                        autosaving--;
                        stopLoading(result.success, smallLoader);
                        saveButtons.forEach(button => {
                            button.disabled = false;
                        });
                        lastSave.forEach(save => {
                            save.innerHTML = (new Date()).toLocaleTimeString();
                        });
	                    if (result.reload) {
		                    window.location.reload();
	                    }
                    })
                    .catch(err => {
                        console.error(err);
                        autosaving--;
                        stopLoading(false, smallLoader);
                        saveButtons.forEach(button => {
                            button.disabled = false;
                        });
                    });
            } else if (!smallLoader) {
                stopLoading(true, false);
                lastSave.forEach(save => {
                    save.innerHTML = (new Date()).toLocaleTimeString();
                });
            }
        };

        form.addEventListener("autosave", () => save());

        saveButtons.forEach(button => {
            button.addEventListener("click", e => {
                if (button.dataset.prevent) {
                    e.preventDefault();
                }
                save(false);
            });
        })

        setInterval(save, 10000);
    });
}