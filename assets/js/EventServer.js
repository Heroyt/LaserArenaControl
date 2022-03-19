const EventServerInstance = new EventServer();
export default EventServerInstance;

/**
 * Singleton class for handling connection to WS event server
 */
class EventServer {
	constructor() {
		this.ws = new WebSocket(webSocketEventURI);
		this.listeners = {};
		this.ws.onmessage = e => {
			const message = e.data.trim();
			this.triggerEvent(message, e);
		};
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