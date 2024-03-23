import * as navigationPreload from 'workbox-navigation-preload';
import {NetworkFirst, StaleWhileRevalidate} from 'workbox-strategies';
import {NavigationRoute, registerRoute, Route} from 'workbox-routing';
import {precacheAndRoute} from "workbox-precaching";

// Give TypeScript the correct global.
declare const self: ServiceWorkerGlobalScope;

self.addEventListener('install', () => {
    self.skipWaiting();
});

precacheAndRoute(self.__WB_MANIFEST);
navigationPreload.enable();

const navigationRoute = new NavigationRoute(new NetworkFirst({
    cacheName: 'navigations'
}));

registerRoute(navigationRoute);

const staticAssetsRoute = new Route(({request}) => {
    return ['image', 'script', 'style'].includes(request.destination);
}, new StaleWhileRevalidate({
    cacheName: 'static-assets'
}));
registerRoute(staticAssetsRoute);
