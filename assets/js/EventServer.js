const EventServerInstance = new EventServer();
export default EventServerInstance;

/**
 * Singleton class for handling connection to WS event server
 */
class EventServer {
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
		document.querySelectorAll(`[data-toggle="event"]`).forEach(btn => {
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
		this.ws.onerror = err => {
			console.error('Socket encountered error: ', err.message, 'Closing socket');
			this.ws.close();
		}
	}

	/**
	 * Add a callback to event(s)
	 *
	 * @param {string|string[]} event
	 * @param {handler} callback
	 */
	addEventListener(event, callback) {
		if (typeof event === 'string') {
			event = [event];
		}
		if (typeof event === 'object' && event.constructor.toString().indexOf("Array") > -1) {
			event.forEach(e => {
				if (!this.listeners[e]) {
					this.listeners[e] = [];
				}
				this.listeners[e].push(callback);
			})
		}
	}

	/**
	 * Trigger a specific event
	 *
	 * @param {string} event
	 * @param {MessageEvent} data
	 */
	triggerEvent(event, data) {
		if (this.listeners[event]) {
			this.listeners[event].forEach(callback => {
				callback(data);
			});
		}
	}
}