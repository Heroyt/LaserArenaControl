import {Modal} from 'bootstrap';
import EventServerInstance from '../../EventServer';
import {startLoading, stopLoading} from '../../loaders';
import {
	controlCancelDownload,
	controlLoadSafe,
	controlRetryDownload,
	controlStartSafe,
	controlStop,
	getCurrentControlStatus,
} from '../../api/endpoints/control';
import {ResponseError} from '../../includes/apiClient';
import {triggerNotificationError} from '../../includes/notifications';

export enum GameStatus {
    DOWNLOAD, STANDBY, ARMED, PLAYING,
}

export default class Control {
    private loadBtn: HTMLButtonElement;
    private startBtn: HTMLButtonElement;
    private stopBtn: HTMLButtonElement;
    private statusGettingInProgress: boolean = false;
    private downloadModalElem: HTMLDivElement;
    private downloadModal: Modal;
    private retryDownloadBtn: HTMLButtonElement;
    private cancelDownloadBtn: HTMLButtonElement;
    private updateStatusInterval: ReturnType<typeof setInterval>;
    private resultsLoadRetryTimer: ReturnType<typeof setTimeout>;

    constructor(loadBtn: HTMLButtonElement, startBtn: HTMLButtonElement, stopBtn: HTMLButtonElement) {
        this.loadBtn = loadBtn;
        this.startBtn = startBtn;
        this.stopBtn = stopBtn;

        this.initModal();

        this.updateCurrentStatus();
        // Update current status every minute
        this.updateStatusInterval = setInterval(() => {
            this.updateCurrentStatus();
        }, 60000);
        this.resultsLoadRetryTimer = null;

        EventServerInstance.addEventListener(['game-imported', 'game-started', 'game-loaded'], () => {
            this.updateCurrentStatus();
        });

        this.stopBtn.addEventListener('click', () => {
            this.stopGame();
        })
    }

    private _currentStatus: GameStatus = GameStatus.STANDBY;

    get currentStatus(): GameStatus {
        return this._currentStatus;
    }

    updateCurrentStatus(): void {
        if (this.statusGettingInProgress) {
            return;
        }
        this.statusGettingInProgress = true;
        this.getCurrentStatus()
            .then(response => {
                this.statusGettingInProgress = false;
                this.setCurrentStatus(response.status);
            })
            .catch(e => {
		            triggerNotificationError(e);
                this.statusGettingInProgress = false;
            })
    }

    getCurrentStatus() {
	    return getCurrentControlStatus(system.id);
    }

    setCurrentStatus(status: string): void {
        this.loadBtn.disabled = false;
        this.startBtn.disabled = false;
        this.stopBtn.disabled = false;
        if (this.currentStatus === GameStatus.DOWNLOAD && status !== 'DOWNLOAD') {
            this.cancelDownloadModal();
        }
        switch (status) {
            case 'DOWNLOAD':
                this.loadBtn.disabled = true;
                this.startBtn.disabled = true;
                this.stopBtn.disabled = true;
                this._currentStatus = GameStatus.DOWNLOAD;
                this.triggerDownloadModal();
                break;
            case 'STANDBY':
                this._currentStatus = GameStatus.STANDBY;
                this.stopBtn.disabled = true;
                break;
            case 'ARMED':
                this._currentStatus = GameStatus.ARMED;
                break;
            case 'PLAYING':
                this._currentStatus = GameStatus.PLAYING;
                this.loadBtn.disabled = true;
                this.startBtn.disabled = true;
                break;
        }
    }

    loadGame(mode: string, callback: null | (() => void) = null) {
        startLoading(true);
	    controlLoadSafe(mode, system.id)
            .then(response => {
                if (response.status !== 'ok') {
                    this.setCurrentStatus(response.status);
                    stopLoading(false, true);
                    return;
                }
                this.setCurrentStatus('ARMED');
                if (callback) {
                    callback();
                }
                stopLoading(true, true);
            })
            .catch(async error => {
		            triggerNotificationError(error);
                stopLoading(false, true);
                if (error instanceof ResponseError) {
                    const data = await error.getDataFromResponse()
                    if (data && data.message && data.message === 'DOWNLOAD') {
                        this.setCurrentStatus('DOWNLOAD');
                    }
                }
            });
    }

    stopGame() {
        startLoading(true);
        this.getCurrentStatus()
            .then(response => {
                if (response.status) {
                    switch (response.status) {
                        case 'STANDBY':
                            this._currentStatus = GameStatus.STANDBY;
                            break;
                        case 'ARMED':
                        case 'PLAYING':
                            this._currentStatus = response.status === 'ARMED' ? GameStatus.ARMED : GameStatus.PLAYING;
	                        controlStop(system.id)
                                .then(() => {
                                    stopLoading(true, true);
                                    this.setCurrentStatus('STANDBY');
                                })
                                .catch(error => {
		                                triggerNotificationError(error);
                                    if (error.data && error.data.message && error.data.message === 'DOWNLOAD') {
                                        this.setCurrentStatus('DOWNLOAD');
                                    }
                                    stopLoading(false, true);
                                });
                            break;
                        case 'DOWNLOAD':
                            this.setCurrentStatus('DOWNLOAD');
                            stopLoading(false, true);
                            break;
                    }
                }
            });
    }

    startGame(data: FormData, loadStartGame: (data: FormData, callback: null | (() => void)) => void): void {
        startLoading(true);
        this.getCurrentStatus()
            .then(response => {
                stopLoading(true, true);
                if (response.status) {
                    switch (response.status) {
                        case 'STANDBY':
                            this._currentStatus = GameStatus.STANDBY;
                            loadStartGame(data, null);
                            break;
                        case 'ARMED':
                            this._currentStatus = GameStatus.ARMED;
                            this.sendStart();
                            break;
                        case 'PLAYING':
                            this._currentStatus = GameStatus.PLAYING;
                            // Cannot start while playing the game
                            stopLoading(false, true);
                            break;
                        case 'DOWNLOAD':
                            this.setCurrentStatus('DOWNLOAD');
                            stopLoading(false, true);
                            break;
                    }
                }
                stopLoading(true, true);
            })
            .catch(error => {
		            triggerNotificationError(error);
                stopLoading(false, true);
            })

    }

    sendStart() {
        startLoading(true);
	    controlStartSafe(null, system.id)
            .then(response => {
                if (response.status !== 'ok') {
                    this.setCurrentStatus(response.status);
                    stopLoading(false);
                    return;
                }
                this.setCurrentStatus('PLAYING');
                stopLoading(true);
            })
            .catch(async error => {
		            triggerNotificationError(error);
                if (error instanceof ResponseError) {
                    const data = await error.getDataFromResponse()
                    if (data && data.message && data.message === 'DOWNLOAD') {
                        this.setCurrentStatus('DOWNLOAD');
                    }
                }
                stopLoading(false);
            });
    }

    loadStart(mode: string, callback: null | (() => void) = null) {
        startLoading(true);
	    controlStartSafe(mode, system.id)
            .then(response => {
                if (response.status !== 'ok') {
                    this.setCurrentStatus(response.status);
                    stopLoading(false, true);
                    return;
                }
                this.setCurrentStatus('ARMED');
                if (callback) {
                    callback();
                }
                stopLoading(true, true);
            })
            .catch(async error => {
	            triggerNotificationError(error);
                stopLoading(false, true);
                if (error instanceof ResponseError) {
                    const data = await error.getDataFromResponse()
                    if (data && data.message && data.message === 'DOWNLOAD') {
                        this.setCurrentStatus('DOWNLOAD');
                    }
                }
            });
    }

    private initModal() {
        this.downloadModalElem = document.getElementById('scoresDownloadModal') as HTMLDivElement;
        this.downloadModal = new Modal(this.downloadModalElem);
        this.retryDownloadBtn = document.getElementById('retryDownload') as HTMLButtonElement;
        this.cancelDownloadBtn = document.getElementById('cancelDownload') as HTMLButtonElement;

        this.retryDownloadBtn.addEventListener('click', () => {
            if (this.currentStatus !== GameStatus.DOWNLOAD) {
                this.cancelDownloadModal();
                return;
            }

            if (this.retryDownloadBtn.disabled) {
                return;
            }

            startLoading(true);
	        controlRetryDownload(system.id)
                .then(() => {
                    stopLoading(true, true);
                })
                .catch(error => {
		                triggerNotificationError(error);
                    stopLoading(false, true);
                });
        });
        this.cancelDownloadBtn.addEventListener('click', () => {
            if (this.currentStatus !== GameStatus.DOWNLOAD) {
                this.cancelDownloadModal();
                return;
            }

            if (this.cancelDownloadBtn.disabled) {
                return;
            }

            startLoading(true);
	        controlCancelDownload(system.id)
                .then(() => {
                    stopLoading(true, true);
                })
                .catch(error => {
		                triggerNotificationError(error);
                    stopLoading(false, true);
                });
        });
    }

    private cancelDownloadModal() {
        this.downloadModal.hide();

        // Reset the update status interval
        clearInterval(this.updateStatusInterval);
        this.updateStatusInterval = setInterval(() => {
            this.updateCurrentStatus();
        }, 60000);

        this.retryDownloadBtn.disabled = false;
        this.cancelDownloadBtn.disabled = false;
        if (this.resultsLoadRetryTimer) {
            clearTimeout(this.resultsLoadRetryTimer);
        }
    }

    private triggerDownloadModal() {
        this.downloadModal.show();

        // Make the update status interval faster to fetch more real-time data
        clearInterval(this.updateStatusInterval);
        this.updateStatusInterval = setInterval(() => {
            this.updateCurrentStatus();
        }, 5000);

        if (!this.resultsLoadRetryTimer) {
            this.retryDownloadBtn.disabled = true;
            this.cancelDownloadBtn.disabled = true;
            this.resultsLoadRetryTimer = setTimeout(() => {
                this.retryDownloadBtn.disabled = false;
                this.cancelDownloadBtn.disabled = false;
            }, 15000);
        }
    }

}