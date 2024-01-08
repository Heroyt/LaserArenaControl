import {startLoading, stopLoading} from "../../loaders";
import {updateGameGroup} from "../../api/endpoints/gameGroups";

export default function initGroupsSettings() {
    const activeGroups = document.getElementById('active-groups') as HTMLDivElement;
    const inactiveGroups = document.getElementById('inactive-groups') as HTMLDivElement;

    const groups = document.querySelectorAll('.game-group') as NodeListOf<HTMLDivElement>;

    groups.forEach(group => {
        const id = parseInt(group.dataset.id);
        let active = group.dataset.active === '1';
        const groupName = group.querySelector('.group-name') as HTMLInputElement;
        const deleteBtn = group.querySelector('.delete') as HTMLButtonElement;
        const enableBtn = group.querySelector('.enable') as HTMLButtonElement;

        let timeout: NodeJS.Timeout = null;
        groupName.addEventListener('input', () => {
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(() => {
                startLoading(true);
                updateGameGroup(id, {name: groupName.value})
                    .then(() => {
                        stopLoading(true, true);
                    })
                    .catch(() => {
                        stopLoading(false, true);
                    })
            }, 1000);
        });

        deleteBtn.addEventListener('click', () => {
            if (!active) {
                return;
            }
            startLoading(true);
            updateGameGroup(id, {active: false})
                .then(() => {
                    stopLoading(true, true);
                    deleteBtn.classList.add('d-none');
                    enableBtn.classList.remove('d-none');
                    active = false;
                    group.dataset.active = '0';
                    inactiveGroups.prepend(group);
                })
                .catch(() => {
                    stopLoading(false, true);
                })
        });

        enableBtn.addEventListener('click', () => {
            if (active) {
                return;
            }
            startLoading(true);
            updateGameGroup(id, {active: true})
                .then(() => {
                    stopLoading(true, true);
                    deleteBtn.classList.remove('d-none');
                    enableBtn.classList.add('d-none');
                    active = true;
                    group.dataset.active = '1';
                    activeGroups.append(group);
                })
                .catch(() => {
                    stopLoading(false, true);
                })
        });
    })
}