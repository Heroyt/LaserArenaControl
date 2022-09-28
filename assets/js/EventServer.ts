export interface EvWindow extends Window {
	EventServerInstance: EventServer
}

declare global {
	const webSocketEventURI: string;
}
declare let window: EvWindow;

/**
 * Singleton class for handling connection to WS event server
 */
class EventServer {

	ws: WebSocket | null;
	listeners: {
		[index: string]: ((ev?: MessageEvent) => {})[],
	}

	constructor() {
		this.ws = null;
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
			btn.addEventListener('click', () => {
				this.ws.send(event + '\n');
				console.log("Event trigger:", event);
			});
			btn.addEventListener('trigger-event', () => {
				this.ws.send(event + '\n');
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
			this.ws = new WebSocket(webSocketEventURI);
		} catch (e) {
			console.error(e.message);
			setTimeout(this.connect, 1000); // Retry in one second
		}
		this.ws.onmessage = e => {
			const message = e.data.trim();
			this.triggerEvent(message, e);
		};
		this.ws.onclose = e => {
			console.log('Socket is closed. Reconnect will be attempted in 1 second.', e.reason);
			setTimeout(() => {
				this.connect(); // Automatically reconnect on close
			}, 500);
		}
		this.ws.onerror = (err: ErrorEvent) => {
			console.error('Socket encountered error: ', err.message, 'Closing socket');
			this.ws.close();
		}
	}

	/**
	 * Add a callback to event(s)
	 */
	addEventListener(event: string | string[], callback: (ev?: MessageEvent) => {}) {
		if (typeof event === 'string') {
			event = [event];
		}
		if (typeof event === 'object' && event.constructor.toString().indexOf("Array") > -1) {
			event.forEach((e: string) => {
				if (!this.listeners[e]) {
					this.listeners[e] = [];
				}
				this.listeners[e].push(callback);
			})
		}
	}

	/**
	 * Trigger a specific event
	 */
	triggerEvent(event: string, data: MessageEvent) {
		if (this.listeners[event]) {
			this.listeners[event].forEach(callback => {
				callback(data);
			});
		}
	}
}

const EventServerInstance = new EventServer();
window.EventServerInstance = EventServerInstance;
export default EventServerInstance;