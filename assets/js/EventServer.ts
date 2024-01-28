import {triggerEvent} from "./api/endpoints/events";

export interface EvWindow extends Window {
	EventServerInstance: EventServer
}

declare global {
    const eventSourceURI: string;
}
declare let window: EvWindow;

/**
 * Singleton class for handling connection to WS event server
 */
class EventServer {

    source: EventSource | null;
	listeners: {
        [index: string]: { callback: (((ev?: MessageEvent) => void) | (() => void)), wait: boolean }[],
	}

    private events: Set<string> = new Set;

    private waitingListeners: (((ev?: MessageEvent) => void) | (() => void))[] = [];

	constructor() {
        this.source = null;
		this.connect();
		this.listeners = {};
		this.initTriggers();
	}

	/**
	 * Initializes all buttons that trigger events on the event server
	 */
	initTriggers() {
		(document.querySelectorAll(`[data-toggle="event"]`) as NodeListOf<HTMLButtonElement>).forEach(btn => {
			const event = btn.dataset.event;
			if (!event || event === '') {
				return;
			}
			console.log("Found event trigger:", event, btn);
            btn.addEventListener('click', async () => {
                await triggerEvent(event);
				console.log("Event trigger:", event);
			});
            btn.addEventListener('trigger-event', async () => {
                await triggerEvent(event);
				console.log("Event trigger:", event);
			});
		});
	}

	/**
	 * Connect websocket to server
	 *
	 * @post Connection to websocket server is established
	 */
	connect() {
		try {
            this.source = new EventSource(eventSourceURI);
            console.log(this.source.readyState);
		} catch (e) {
			console.error(e.message);
			setTimeout(this.connect, 1000); // Retry in one second
		}
        this.source.onopen = e => {
            console.log('Event source open', e);
        }
        this.source.onmessage = e => {
            console.log(e.data);
			const message = e.data.trim();
			this.triggerEvent(message, e);
		};
        this.source.addEventListener('event', (e: MessageEvent<string>) => {
            console.log(e);
            this.triggerEvent(e.data.replace('events:', ''), e)
        });
        this.source.addEventListener('data', (e: MessageEvent<string>) => {
            console.log(e);
            this.triggerData(e)
        });
        this.source.onerror = (err: ErrorEvent) => {
            console.error('SSE encountered error: ', err.message, 'Closing SEE');
            this.source.close();
            setTimeout(this.connect, 1000); // Retry in one second
		}
	}

	/**
	 * Add a callback to event(s)
	 */
    addEventListener(event: string | string[], callback: ((ev?: MessageEvent) => void) | (() => void), wait: boolean = false) {
		if (typeof event === 'string') {
			event = [event];
		}
		if (typeof event === 'object' && event.constructor.toString().indexOf("Array") > -1) {
			event.forEach((e: string) => {
                if (!this.events.has(e)) {
                    this.source.addEventListener('events:' + e, ev => {
                        this.triggerEvent(e, ev);
                    });
                    this.events.add(e);
                }
				if (!this.listeners[e]) {
					this.listeners[e] = [];
				}
                this.listeners[e].push({callback, wait});
			})
		}
	}

	/**
	 * Trigger a specific event
	 */
	triggerEvent(event: string, data: MessageEvent) {
		if (this.listeners[event]) {
            this.listeners[event].forEach(listener => {
                if (listener.wait) {
                    this.waitingListeners.push(listener.callback);
                    return;
                }
                listener.callback(data);
            });
        }
    }

    private triggerData(data: MessageEvent) {
        this.waitingListeners.forEach(callback => {
            callback(data);
        });
        this.waitingListeners = [];
    }
}

const EventServerInstance = new EventServer();
window.EventServerInstance = EventServerInstance;
export default EventServerInstance;