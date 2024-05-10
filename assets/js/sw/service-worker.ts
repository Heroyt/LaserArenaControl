// noinspection ES6PreferShortImport

import * as navigationPreload from 'workbox-navigation-preload';
import {NetworkFirst, StaleWhileRevalidate} from 'workbox-strategies';
import {NavigationRoute, registerRoute, Route} from 'workbox-routing';
import {precacheAndRoute} from 'workbox-precaching';
import {Events} from '../../../node_modules/hls.js/src/events.ts';
import {enableLogs, ILogFunction, logger} from '../../../node_modules/hls.js/src/utils/logger.ts';
import Transmuxer, {isPromise} from '../../../node_modules/hls.js/src/demux/transmuxer.ts';
import type {ChunkMetadata, TransmuxerResult} from '../../../node_modules/hls.js/src/types/transmuxer.ts';
import {ErrorDetails, ErrorTypes} from '../../../node_modules/hls.js/src/errors.ts';
import type {RemuxedTrack, RemuxerResult} from '../../../node_modules/hls.js/src/types/remuxer.ts';

// Give TypeScript the correct global.
declare const self: ServiceWorkerGlobalScope;

// Dynamic Modules
declare global {
	const __USE_ALT_AUDIO__: boolean;
	const __USE_EME_DRM__: boolean;
	const __USE_SUBTITLES__: boolean;
	const __USE_CMCD__: boolean;
	const __USE_CONTENT_STEERING__: boolean;
	const __USE_VARIABLE_SUBSTITUTION__: boolean;
	const __USE_M2TS_ADVANCED_CODECS__: boolean;
	const __USE_MEDIA_CAPABILITIES__: boolean;
}

// __IN_WORKER__ is provided from a closure call around the final UMD bundle.
declare const __IN_WORKER__: boolean;

globalThis.__USE_SUBTITLES__ = true;
globalThis.__USE_ALT_AUDIO__ = true;
globalThis.__USE_EME_DRM__ = true;
globalThis.__USE_CMCD__ = true;
globalThis.__USE_CONTENT_STEERING__ = true;
globalThis.__USE_VARIABLE_SUBSTITUTION__ = true;
globalThis.__USE_M2TS_ADVANCED_CODECS__ = true;
globalThis.__USE_MEDIA_CAPABILITIES__ = true;

self.addEventListener('install', () => {
	self.skipWaiting();
});

precacheAndRoute(self.__WB_MANIFEST);
navigationPreload.enable();

const navigationRoute = new NavigationRoute(new NetworkFirst({
	cacheName: 'navigations',
}));

registerRoute(navigationRoute);

const staticAssetsRoute = new Route(({request}) => {
	return ['image', 'script', 'style'].includes(request.destination);
}, new StaleWhileRevalidate({
	cacheName: 'static-assets',
}));
registerRoute(staticAssetsRoute);

// HLS.js
if (typeof __IN_WORKER__ !== 'undefined' && __IN_WORKER__) {
	startWorker(self);
}

function startWorker(self) {
	const observer = new EventEmitter();
	const forwardMessage = (ev, data) => {
		self.postMessage({event: ev, data: data});
	};

	// forward events to main thread
	observer.on(Events.FRAG_DECRYPTED, forwardMessage);
	observer.on(Events.ERROR, forwardMessage);

	// forward logger events to main thread
	const forwardWorkerLogs = () => {
		for (const logFn in logger) {
			const func: ILogFunction = (message?) => {
				forwardMessage('workerLog', {
					logType: logFn,
					message,
				});
			};

			logger[logFn] = func;
		}
	};

	self.addEventListener('message', (ev) => {
		const data = ev.data;
		switch (data.cmd) {
			case 'init': {
				const config = JSON.parse(data.config);
				self.transmuxer = new Transmuxer(
					observer,
					data.typeSupported,
					config,
					data.vendor,
					data.id,
				);
				enableLogs(config.debug, data.id);
				forwardWorkerLogs();
				forwardMessage('init', null);
				break;
			}
			case 'configure': {
				self.transmuxer.configure(data.config);
				break;
			}
			case 'demux': {
				const transmuxResult: TransmuxerResult | Promise<TransmuxerResult> =
					self.transmuxer.push(
						data.data,
						data.decryptdata,
						data.chunkMeta,
						data.state,
					);
				if (isPromise(transmuxResult)) {
					self.transmuxer.async = true;
					transmuxResult
						.then((data) => {
							emitTransmuxComplete(self, data);
						})
						.catch((error) => {
							forwardMessage(Events.ERROR, {
								type: ErrorTypes.MEDIA_ERROR,
								details: ErrorDetails.FRAG_PARSING_ERROR,
								chunkMeta: data.chunkMeta,
								fatal: false,
								error,
								err: error,
								reason: `transmuxer-worker push error`,
							});
						});
				} else {
					self.transmuxer.async = false;
					emitTransmuxComplete(self, transmuxResult);
				}
				break;
			}
			case 'flush': {
				const id = data.chunkMeta;
				let transmuxResult = self.transmuxer.flush(id);
				const asyncFlush = isPromise(transmuxResult);
				if (asyncFlush || self.transmuxer.async) {
					if (!isPromise(transmuxResult)) {
						transmuxResult = Promise.resolve(transmuxResult);
					}
					transmuxResult
						.then((results: Array<TransmuxerResult>) => {
							handleFlushResult(self, results as Array<TransmuxerResult>, id);
						})
						.catch((error) => {
							forwardMessage(Events.ERROR, {
								type: ErrorTypes.MEDIA_ERROR,
								details: ErrorDetails.FRAG_PARSING_ERROR,
								chunkMeta: data.chunkMeta,
								fatal: false,
								error,
								err: error,
								reason: `transmuxer-worker flush error`,
							});
						});
				} else {
					handleFlushResult(
						self,
						transmuxResult as Array<TransmuxerResult>,
						id,
					);
				}
				break;
			}
			default:
				break;
		}
	});
}

function emitTransmuxComplete(
	self: any,
	transmuxResult: TransmuxerResult,
): boolean {
	if (isEmptyResult(transmuxResult.remuxResult)) {
		return false;
	}
	const transferable: Array<ArrayBuffer> = [];
	const {audio, video} = transmuxResult.remuxResult;
	if (audio) {
		addToTransferable(transferable, audio);
	}
	if (video) {
		addToTransferable(transferable, video);
	}
	self.postMessage(
		{event: 'transmuxComplete', data: transmuxResult},
		transferable,
	);
	return true;
}

// Converts data to a transferable object https://developers.google.com/web/updates/2011/12/Transferable-Objects-Lightning-Fast)
// in order to minimize message passing overhead
function addToTransferable(
	transferable: Array<ArrayBuffer>,
	track: RemuxedTrack,
) {
	if (track.data1) {
		transferable.push(track.data1.buffer);
	}
	if (track.data2) {
		transferable.push(track.data2.buffer);
	}
}

function handleFlushResult(
	self: any,
	results: Array<TransmuxerResult>,
	chunkMeta: ChunkMetadata,
) {
	const parsed = results.reduce(
		(parsed, result) => emitTransmuxComplete(self, result) || parsed,
		false,
	);
	if (!parsed) {
		// Emit at least one "transmuxComplete" message even if media is not found to update stream-controller state to PARSING
		self.postMessage({event: 'transmuxComplete', data: results[0]});
	}
	self.postMessage({event: 'flush', data: chunkMeta});
}

function isEmptyResult(remuxResult: RemuxerResult) {
	return (
		!remuxResult.audio &&
		!remuxResult.video &&
		!remuxResult.text &&
		!remuxResult.id3 &&
		!remuxResult.initSegment
	);
}