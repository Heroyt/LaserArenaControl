export default class ActivityMonitor {

    private inactiveTimer: number | NodeJS.Timeout;
    private readonly screen: HTMLDivElement;
    private players: HTMLDivElement;
    private shown: boolean = false;

    constructor() {
        this.screen = document.getElementById('inactiveScreen') as HTMLDivElement;
        if (!this.screen) {
            return;
        }
        this.players = this.screen.querySelector('.game-players');

        this.reset();

        document.addEventListener('mousemove', () => {
            this.reset();
        });
        document.addEventListener('touchstart', () => {
            this.reset();
        });
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && e.key === 'x') {
                this.show();
                return;
            }
            this.reset();
        });
        document.addEventListener('active-game-data-loaded', () => {
            if (this.shown) {
                if (activeGame) {
                    this.show();
                } else {
                    this.hide();
                    this.shown = true;
                }
            }
            this.setupGame();
        });
    }

    reset() {
        if (this.inactiveTimer) {
            clearTimeout(this.inactiveTimer);
        }
        if (this.shown) {
            this.hide();
        }
        this.inactiveTimer = setTimeout(() => this.show(), 15000);
    }

    show() {
        this.shown = true;
        if (!activeGame) {
            return;
        }
        this.screen.style.display = 'flex';
        this.screen.classList.remove('hidden');
    }

    hide() {
        this.shown = false;
        this.screen.classList.add('hidden');
        setTimeout(() => {
            this.screen.style.display = 'none';
        }, 300);
    }

    private setupGame() {
        this.players.innerHTML = '';
        if (!activeGame) {
            return;
        }
        Object.values(activeGame.players).forEach(player => {
            this.players.innerHTML += `<div class="badge bg-team-${activeGame.system}-${player.teamNum}">${player.name}</div>`;
        });
    }

}