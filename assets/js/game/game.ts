import Player from "./player";
import Team from "./team";
import {shuffle} from "../includes/functions";
import {Tooltip} from "bootstrap";
import {GameData, Variation, VariationsValue} from '../interfaces/gameInterfaces';
import CustomLoadMode from "./customLoadMode";
// @ts-ignore
import Sortable from "sortablejs/modular/sortable.core.esm.js";
import CustomSwapPlugin from "./customSwapPlugin";
import {collapseClose, collapseShow} from "../includes/collapse";

declare global {
    const messages: { [index: string]: string };
}

interface PlayerGroup {
    team: string,
    skill: number,
    players: Player[]
}

interface VariationMemory {
    [index: string]: {
        [index: number]: string
    }
}

export default class Game {

    players: Map<String, Player>;
    teams: Map<String, Team>;

    $group: HTMLSelectElement;
    $table: HTMLSelectElement;

    $gameMode: HTMLSelectElement;
    $musicMode: HTMLSelectElement;
    $groupMusicModes: HTMLInputElement;
    $teams: NodeListOf<HTMLInputElement>;
    $maxSkill: NodeListOf<HTMLInputElement>;
    $modeVariations: HTMLDivElement;
    $variationsHideBtn: HTMLButtonElement;

    $shuffleTeams: HTMLButtonElement;
    $shuffleFairTeams: HTMLButtonElement;

    $soloHide: NodeListOf<HTMLElement>;

    $clearAll: HTMLButtonElement;

    teamShuffleTooltip: Tooltip;
    noPlayersTooltip: Tooltip;

    maxSkill: 3 | 6 = 3;

    loadedModeScript: CustomLoadMode | null = null;

    variationMemory: VariationMemory;

    sortable: Sortable;

    constructor() {

        this.variationMemory = JSON.parse(window.localStorage.getItem('modeVariationMemory'));
        if (!this.variationMemory) {
            this.variationMemory = {};
            this.updateVariationMemory();
        }

        this.players = new Map;
        this.teams = new Map;

        this.$group = document.getElementById('group-select') as HTMLSelectElement;
        this.$table = document.getElementById('table-select') as HTMLSelectElement;

        this.$gameMode = document.getElementById('game-mode-select') as HTMLSelectElement;
        this.$musicMode = document.getElementById('music-select') as HTMLSelectElement;
        this.$groupMusicModes = document.getElementById('music-mode-grouped') as HTMLInputElement;
        this.$teams = document.querySelectorAll('#teams-random .team-color-input');
        this.$maxSkill = document.querySelectorAll('.maxSkill');
        this.$modeVariations = document.getElementById('game-mode-variations-wrapper') as HTMLDivElement;
        this.$variationsHideBtn = document.getElementById('hide-variations') as HTMLButtonElement;

        this.$shuffleTeams = document.getElementById('random-teams') as HTMLButtonElement;
        this.$shuffleFairTeams = document.getElementById('random-fair-teams') as HTMLButtonElement;

        this.$soloHide = document.querySelectorAll('.solo-hide');

        this.$clearAll = document.getElementById('clear-all') as HTMLButtonElement;

        this.$groupMusicModes.checked = window.localStorage.getItem('group-music-mode') === '1';
        if (this.$groupMusicModes.checked) {
            this.groupMusicModes();
        } else {
            this.unGroupMusicModes();
        }

        this.teamShuffleTooltip = new Tooltip(
            document.getElementById('team-random-select'),
            {
                title: messages.missingShuffleTeam,
                trigger: 'manual',
                customClass: 'tooltip-danger',
            }
        );
        this.noPlayersTooltip = new Tooltip(
            document.querySelector('.vest-row'),
            {
                title: messages.noPlayersActive,
                trigger: 'manual',
                customClass: 'tooltip-danger',
            }
        );

        (document.querySelectorAll('.vest-row') as NodeListOf<HTMLDivElement>).forEach(row => {
            const vestNum = row.dataset.vest;
            this.players.set(vestNum, new Player(vestNum, row, this));

            row.addEventListener('player-activate', () => {
                this.noPlayersTooltip.hide();
            })
        });
        (document.querySelectorAll('.team-row') as NodeListOf<HTMLDivElement>).forEach(row => {
            const key = row.dataset.key;
            this.teams.set(key, new Team(key, row, this));
        });
        console.log(this);

        const variations: {
            [index: number]: VariationsValue[]
        } = JSON.parse((this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`) as HTMLOptionElement).dataset.variations);
        console.log(variations);
        this.updateModeVariations(variations);

        // @ts-ignore
        Sortable.mount(new CustomSwapPlugin());

        this.sortable = new Sortable(document.getElementById('vestsWrapper'), {
            handle: '.handle',
            swap: true,
            swapClass: 'highlight',
            game: this,
        });

        this.initEvents();
    }

    updateAllowedTeams(teams: string[]) {
        this.players.forEach(player => {
            player.updateAllowedTeams(teams);
        });

        this.$teams.forEach(input => {
            if (!teams.includes(input.value)) {
                input.checked = false;
                input.classList.add('hide');
                input.classList.remove('show');
            } else {
                input.classList.remove('hide');
                input.classList.add('show');
            }
            input.dispatchEvent(new Event('change', {bubbles: true}));
        });

        const shown = document.querySelectorAll('#teams-random .team-color-input:not(.hide) + label') as NodeListOf<HTMLLabelElement>;
        shown.forEach((label, key) => {
            if (key === 0) {
                label.classList.add('rounded-start');
                label.classList.remove('rounded-end');
            } else if (key === shown.length - 1) {
                label.classList.remove('rounded-start');
                label.classList.add('rounded-end');
            } else {
                label.classList.remove('rounded-start', 'rounded-end');
            }
        })
    }

    initEvents() {
        this.$clearAll.addEventListener('click', () => {
            this.clearAll();
        });

        this.$gameMode.addEventListener('change', () => {
            if (this.loadedModeScript !== null) {
                this.loadedModeScript.cancel();
                this.loadedModeScript = null;
            }

            const option = this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`) as HTMLOptionElement;
            const type = this.getModeType();
            console.log(type, this.$soloHide);

            this.$soloHide.forEach(elem => {
                if (type === 'SOLO') {
                    elem.classList.add('d-none');
                } else {
                    elem.classList.remove('d-none');
                }
            });

            const teams = JSON.parse(
                option.dataset.teams
            );
            this.updateAllowedTeams(teams);

            const variations: {
                [index: number]: VariationsValue[]
            } = JSON.parse((this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`) as HTMLOptionElement).dataset.variations);
            console.log(variations);
            this.updateModeVariations(variations);

            if (option.dataset.script) {
                import(
                    /* webpackChunkName: "modes" */
                    `./modes/${option.dataset.script}`
                    )
                    .then((module) => {
                        this.loadedModeScript = new module.default;
                        this.loadedModeScript.init();
                    })
                    .catch(error => {
                        console.error(error);
                    })
            }

            this.$gameMode.dispatchEvent(
                new Event("update", {
                    bubbles: true,
                })
            );
        });

        this.$musicMode.addEventListener('change', () => {
            this.$musicMode.dispatchEvent(
                new Event("update", {
                    bubbles: true,
                })
            );
        });
        this.$groupMusicModes.addEventListener('change', () => {
            window.localStorage.setItem('group-music-mode', this.$groupMusicModes.checked ? '1' : '0');
            if (this.$groupMusicModes.checked) {
                this.groupMusicModes();
            } else {
                this.unGroupMusicModes();
            }
        });

        this.$shuffleTeams.addEventListener('click', () => {
            this.shuffleTeams();
        })

        this.$shuffleFairTeams.addEventListener('click', () => {
            this.shuffleFairTeams();
        })

        this.$group.addEventListener('change', () => {
            this.$group.dispatchEvent(new Event('update', {bubbles: true}));
        })

        this.$teams.forEach($team => {
            $team.addEventListener('change', () => {
                this.teamShuffleTooltip.hide();
            });
        })

        this.$maxSkill.forEach(input => {
            input.addEventListener('change', () => {
                this.updateMaxSkill();
            });
        });

        this.$modeVariations.parentElement.addEventListener('show.bs.collapse', () => {
            console.log('show');
            this.$variationsHideBtn.querySelector('.fa-eye-slash').classList.add('d-none');
            this.$variationsHideBtn.querySelector('.fa-eye').classList.remove('d-none');
        });
        this.$modeVariations.parentElement.addEventListener('hide.bs.collapse', () => {
            console.log('hide');
            this.$variationsHideBtn.querySelector('.fa-eye-slash').classList.remove('d-none');
            this.$variationsHideBtn.querySelector('.fa-eye').classList.add('d-none');
        });
    }

    updateModeVariations(variations: { [index: number]: VariationsValue[] }) {
        // Clear
        this.$modeVariations.innerHTML = '';

        const values = Object.values(variations);
        if (values.length === 0) {
            this.$variationsHideBtn.classList.add('d-none');
            collapseClose(this.$modeVariations.parentElement);
        } else {
            this.$variationsHideBtn.classList.remove('d-none');
            collapseShow(this.$modeVariations.parentElement);
        }
        values.forEach(data => {
            this.addVariation(data[0].variation, data);
        });
    }

    addVariation(variation: Variation, values: VariationsValue[]) {
        const wrapper = document.createElement('div');
        wrapper.classList.add('mb-3', 'mx-3');
        wrapper.innerHTML = `<h6 class="fw-light">${variation.name}:</h6>`;

        if (!this.variationMemory[this.$gameMode.value]) {
            this.variationMemory[this.$gameMode.value] = {};
        }

        const selectedValue = this.variationMemory[this.$gameMode.value][variation.id] ?? null;

        if (values.length < 5) {
            const group = document.createElement('div');
            wrapper.appendChild(group);
            group.classList.add('btn-group');
            if (values.length === 4) {
                group.classList.add('btn-group-sm');
            }

            values.forEach((value, key) => {
                const input = document.createElement('input');
                input.type = 'radio';
                input.classList.add('btn-check');
                input.name = `variation[${variation.id}]`;
                input.id = `variation-${variation.id}-${key}`;
                input.value = value.suffix;
                if ((selectedValue === null && key === 0) || selectedValue === value.suffix) {
                    input.checked = true;
                    this.variationMemory[this.$gameMode.value][variation.id] = value.suffix;
                }
                group.appendChild(input);
                const label = document.createElement('label');
                label.setAttribute('for', input.id);
                label.classList.add('btn', 'btn-outline-primary');
                label.innerText = value.value;
                group.appendChild(label);

                input.addEventListener('change', () => {
                    if (input.checked) {
                        this.variationMemory[this.$gameMode.value][variation.id] = input.value;
                        this.updateVariationMemory();
                    }
                });
            });
        } else {
            const select = document.createElement('select');
            select.classList.add('form-select');
            select.name = `variation[${variation.id}]`;
            select.id = `variation-${variation.id}`;
            wrapper.appendChild(select);

            values.forEach((value) => {
                const option = document.createElement('option');
                option.value = value.suffix;
                option.innerText = value.value;
                select.appendChild(option);
            });

            if (selectedValue !== null) {
                select.value = selectedValue;
            }
            this.variationMemory[this.$gameMode.value][variation.id] = select.value;

            select.addEventListener('change', () => {
                this.variationMemory[this.$gameMode.value][variation.id] = select.value;
                this.updateVariationMemory();
            });
        }

        this.$modeVariations.appendChild(wrapper);

        this.updateVariationMemory();
    }

    clearAll() {
        this.players.forEach(player => {
            player.clear();
        });
        this.teams.forEach(team => {
            team.clear();
        });

        this.$gameMode.value = (this.$gameMode.firstElementChild as HTMLOptionElement).value;
        //this.$musicMode.value = (this.$musicMode.firstElementChild as HTMLOptionElement).value;

        this.$gameMode.dispatchEvent(new Event('update', {bubbles: true}));
        this.$gameMode.dispatchEvent(new Event('change', {bubbles: true}));

        this.$group.value = '';
        this.$group.dispatchEvent(new Event('change', {bubbles: true}));

        this.$table.value = '';
        this.$table.dispatchEvent(new Event('change', {bubbles: true}));

        const e = new Event('clear-all');
        document.dispatchEvent(e);
    }

    /**
     * @return 'SOLO'|'TEAM'
     */
    getModeType(): 'SOLO' | 'TEAM' {
        const type = (this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`) as HTMLOptionElement).dataset.type.toUpperCase();
        if (type === 'SOLO') {
            return type;
        }
        return 'TEAM';
    }

    getSelectedTeams(): string[] {
        const teams: string[] = [];
        this.$teams.forEach($team => {
            if ($team.checked) {
                teams.push($team.value);
            }
        });
        return teams;
    }

    /**
     * @returns {Player[]}
     */
    getActivePlayers(): Player[] {
        const players: Player[] = [];
        this.players.forEach(player => {
            if (player.isActive()) {
                players.push(player);
            }
        });
        return players;
    }

    getActiveTeams(): Team[] {
        if (this.getModeType() === 'SOLO') {
            return [];
        }
        const teams: Team[] = [];
        this.teams.forEach(team => {
            if (team.playerCount > 0) {
                teams.push(team);
            }
        });
        return teams;
    }

    shuffleTeams(): void {
        // Clear all teams
        this.players.forEach(player => {
            player.setTeam('');
        });

        const players = shuffle(this.getActivePlayers());

        if (players.length < 2) {
            this.noPlayersTooltip.show();
            return;
        }

        const teams = shuffle(this.getSelectedTeams());
        const teamCount = teams.length;

        if (teamCount < 2) {
            console.log('Cannot shuffle fairly without at least 2 teams selected.');
            this.teamShuffleTooltip.show();
            return;
        }

        let i = 0;
        players.forEach(player => {
            player.setTeam(teams[i % teamCount]);
            i++;
        });
    }

    shuffleFairTeams(): void {
        // Clear all teams
        this.players.forEach(player => {
            player.setTeam('');
        });

        const players = this.getActivePlayers();

        if (players.length < 2) {
            this.noPlayersTooltip.show();
            return;
        }

        console.log('active players', players);

        const teams = shuffle(this.getSelectedTeams());
        const teamCount = teams.length;

        if (teamCount < 2) {
            console.log('Cannot shuffle fairly without at least 2 teams selected.');
            this.teamShuffleTooltip.show();
            return;
        }

        // Create group objects for each team created
        let groups: PlayerGroup[] = [];
        teams.forEach(team => {
            groups.push({
                team,
                skill: 0,
                players: [],
            });
        });

        // Sort players into N groups by their skill
        const skills: { [index: number]: Player[] } = {};
        players.forEach(player => {
            if (!skills[player.skill]) {
                skills[player.skill] = [];
            }
            skills[player.skill].push(player);
        });

        const skillKeys = Object.keys(skills).map(key => parseInt(key));

        if (skillKeys.length === 1) {
            // All players are in one skill group
            // It makes sense to shuffle the players normally
            this.shuffleTeams();
            return;
        }

        let skillSum = 0;
        // Shuffle skill sets - players
        skillKeys.forEach(key => {
            skills[key] = shuffle(skills[key]);
            skillSum += key * skills[key].length;
        });

        const teamAverage = skillSum / teamCount;
        let sortedPlayers: Player[] = [];
        skillKeys.reverse().forEach(key => {
            sortedPlayers = sortedPlayers.concat(skills[key]);
        });
        console.log('sorted players', sortedPlayers);

        // Fill the teams with players
        let i = 0;
        sortedPlayers.forEach(player => {
            groups[i % teamCount].players.push(player);
            groups[i % teamCount].skill += player.skill;
            i++;
        });

        console.log('groups', groups);
        console.log('sum, average', skillSum, teamAverage);

        // Iterate to mix the teams to be as fair as possible
        const maxIterations = 500;
        const maxIterationsWithoutImprovement = 40;
        let iterationsWithoutImprovement = 0;
        let it;
        for (it = 0; it < maxIterations && iterationsWithoutImprovement < maxIterationsWithoutImprovement; it++) {
            // Randomly select 2 groups
            // Shuffle to prevent using Math.random and then looping to check if the indexes aren't the same
            groups = shuffle(groups);
            // Get the first 2 groups from the shuffled array
            const group1 = groups[0];
            const group2 = groups[1];

            // Score the 2 groups - the lower, the better
            // Score is based from the absolute difference from the calculated average
            const score = Math.abs(group1.skill - teamAverage) + Math.abs(group2.skill - teamAverage);

            // Randomly choose 2 players in groups
            const player1Key = Math.floor(Math.random() * group1.players.length);
            const player2Key = Math.floor(Math.random() * group2.players.length);
            const player1 = group1.players[player1Key];
            const player2 = group2.players[player2Key];

            // The skills are the same, it doesn't matter if they swap.
            if (player1.skill === player2.skill) {
                // Swap with 50% probability.
                if (Math.random() <= 0.5) {
                    group1.players[player1Key] = player2;
                    group2.players[player2Key] = player1;
                }
                iterationsWithoutImprovement++;
                continue;
            }

            // Calculate new skill levels after swapping
            const skill1 = group1.skill - player1.skill + player2.skill;
            const skill2 = group2.skill - player2.skill + player1.skill;

            // Calculate the new score
            const newScore = Math.abs(skill1 - teamAverage) + Math.abs(skill2 - teamAverage);

            // Check improvement
            if (newScore < score) {
                // The score improved after swapping.

                // Swap players
                group1.players[player1Key] = player2;
                group2.players[player2Key] = player1;

                // Update skills
                group1.skill = skill1;
                group2.skill = skill2;

                // Reset iterations without improvements counter
                iterationsWithoutImprovement = 0;
                continue;
            }

            // The score did not improve => do not swap
            iterationsWithoutImprovement++;
        }

        console.log('iterations', it);
        console.log('iterations without improvements', iterationsWithoutImprovement);
        console.log('groups', groups);

        // Assign teams
        groups.forEach(group => {
            group.players.forEach(player => {
                player.setTeam(group.team);
            });
        });
    }

    import(data: GameData) {
        console.log("import game", data);
        this.clearAll();

        if (data.playerCount > 0) {
            const skills = Object.values(data.players).map(playerData => {
                if (playerData.avgSkill) {
                    return playerData.avgSkill;
                }
                return playerData.skill;
            });
            const maxSkill = Math.max(3, ...skills);
            const minSkill = Math.min(...skills);
            const skillStep = (maxSkill - minSkill) / this.maxSkill;
            Object.values(data.players).forEach(playerData => {
                const player = this.players.get(playerData.vest.toString());
                if (!player) {
                    return;
                }
                player.$name.value = playerData.name;
                if (playerData.color !== undefined && playerData.color !== null) {
                    player.setTeam(playerData.color.toString());
                }
                player.setSkill(Math.ceil((playerData.skill - minSkill) / skillStep));
                player.realSkill = playerData.skill;

                if (playerData.vip) {
                    player.setVip(playerData.vip);
                }

                if (playerData.code) {
                    player.setUserCode(playerData.code);
                }
            });
            Object.values(data.teams).forEach(teamData => {
                const team = this.teams.get(teamData.color.toString());
                if (!team) {
                    return;
                }
                team.$name.value = teamData.name;
                team.update();
            });

            if (maxSkill > 3) {
                this.$maxSkill[0].checked = false;
                this.$maxSkill[1].checked = true;
                this.updateMaxSkill();
            }
        }

        const e = new Event('change');
        this.$gameMode.value = data.mode.id.toString();
        this.$gameMode.dispatchEvent(e);
        if (data.music) {
            this.$musicMode.value = data.music.id.toString();

            if (this.$musicMode.value !== data.music.id.toString()) {
                const groupOption = this.$musicMode.querySelector(`[data-m${data.music.id}]`) as HTMLOptionElement;
                if (groupOption) {
                    this.$musicMode.value = groupOption.value;
                }
            }

            this.$musicMode.dispatchEvent(e);
        }
        if (data.group) {
            this.$group.value = data.group.id.toString();
            //console.log('group value', data.group.id.toString(), this.$group.value);

            // If the group is currently not active, it can still be loaded back.
            // In that case an option should be appended because the group still exists, it's just not visible.
            if (this.$group.value !== data.group.id.toString()) {
                const option = document.createElement('option');
                option.value = data.group.id.toString();
                option.innerText = data.group.name;
                this.$group.appendChild(option);
                this.$group.value = data.group.id.toString();
                //console.log('created option', option, this.$group.value);
            }
            document.dispatchEvent(new CustomEvent('game-group-import', {detail: data.group}));
        } else {
            this.$group.value = '';
        }
        if (data.table) {
            this.$table.value = data.table.id.toString();
        } else {
            this.$table.value = '';
        }
        if (!data.group) {
            this.$table.dispatchEvent(e);
        }
        this.$group.dispatchEvent(e);
    }

    reassignPlayerSkills(): void {
        const players = this.getActivePlayers();
        const skills = players.map(player => player.realSkill);

        const maxSkill = Math.max(3, ...skills);
        const minSkill = Math.min(...skills);
        const skillStep = (maxSkill - minSkill) / this.maxSkill;

        players.forEach(player => {
            const skill = player.realSkill;
            player.setSkill(Math.ceil((skill - minSkill) / skillStep));
            player.realSkill = skill; // Keep the real value after update
        });
    }

    export(): GameData {
        const activePlayers = this.getActivePlayers();
        const activeTeams = this.getActiveTeams();

        const musicValue = this.$musicMode.value;
        let musicId: number;
        if (musicValue.startsWith('g-')) {
            const music = JSON.parse(this.$musicMode.querySelector(`[value="${musicValue}"]`).getAttribute('data-music'));
            const ids = Object.keys(music);
            musicId = parseInt(ids[Math.floor(Math.random() * ids.length)]);
        } else {
            musicId = parseInt(musicValue);
        }

        const data: GameData = {
            playerCount: activePlayers.length,
            mode: {
                id: parseInt(this.$gameMode.value),
                name: (this.$gameMode.querySelector(`option[value="${this.$gameMode.value}"]`) as HTMLOptionElement).innerText.trim(),
                type: this.getModeType(),
            },
            music: {
                id: musicId,
            },
            players: {},
            teams: {},
        };

        if (this.$group.value !== '' && this.$group.value !== 'new') {
            data.group = {
                id: parseInt(this.$group.value),
                name: (this.$group.querySelector(`option[value="${this.$group.value}"]`) as HTMLOptionElement).innerText,
                active: true,
            };
        }

        if (this.$table.value !== '') {
            data.table = {
                id: parseInt(this.$table.value),
                name: (this.$table.querySelector(`option[value="${this.$table.value}"]`) as HTMLOptionElement).innerText,
            };
        }

        activePlayers.forEach(player => {
            data.players[player.vest] = {
                name: player.name,
                vip: player.vip,
                vest: typeof player.vest === 'string' ? parseInt(player.vest) : player.vest,
                teamNum: parseInt(player.team),
                color: parseInt(player.team),
                skill: player.skill,
                code: player.userCode,
            }
        });

        activeTeams.forEach(team => {
            data.teams[team.key] = {
                name: team.name,
                color: parseInt(team.key),
                playerCount: team.playerCount,
            }
        });

        return data;
    }

    updateMaxSkill() {
        let skill = 3;
        this.$maxSkill.forEach(input => {
            if (input.checked) {
                skill = parseInt(input.value);
            }
        });
        if (skill === 3 || skill === 6) {
            this.setMaxSkill(skill);
        }
    }

    setMaxSkill(max: 3 | 6) {
        this.maxSkill = max;
        this.players.forEach(player => {
            player.setMaxSkill(max);
        });
        this.reassignPlayerSkills();
    }

    updateVariationMemory(): void {
        window.localStorage.setItem('modeVariationMemory', JSON.stringify(this.variationMemory));
    }

    groupMusicModes(): void {
        const value = this.$musicMode.value;
        this.$musicMode.querySelectorAll('optgroup').forEach(group => {
            const name = group.label;
            const musicModes: { [key: string]: string } = {};
            let selected = false;
            const newOption = document.createElement('option');
            group.querySelectorAll('option').forEach(option => {
                musicModes[option.value] = option.innerText;
                selected = selected || option.value === value;
                newOption.setAttribute('data-m' + option.value, option.value);
            });
            newOption.value = 'g-' + Object.keys(musicModes).join('-');
            newOption.classList.add('music-group');
            newOption.setAttribute('data-music', JSON.stringify(musicModes));
            newOption.innerText = name;
            group.replaceWith(newOption);
            if (selected) {
                this.$musicMode.value = newOption.value;
            }
        })
    }

    unGroupMusicModes(): void {
        const value = this.$musicMode.value;
        this.$musicMode.querySelectorAll('.music-group').forEach((groupOption: HTMLOptionElement) => {
            const selected = groupOption.value === value;
            const groupName = groupOption.innerText;
            const musicModes: { [key: string]: string } = JSON.parse(groupOption.getAttribute('data-music'));
            const newOptgroup = document.createElement('optgroup');
            newOptgroup.label = groupName;
            Object.entries(musicModes).forEach(([id, name]) => {
                const option = document.createElement('option');
                option.value = id;
                option.innerText = name;
                newOptgroup.appendChild(option);
            });
            groupOption.replaceWith(newOptgroup);
            if (selected) {
                const ids = Object.keys(musicModes);
                this.$musicMode.value = ids[Math.floor(Math.random() * ids.length)];
            }
        });
    }
}